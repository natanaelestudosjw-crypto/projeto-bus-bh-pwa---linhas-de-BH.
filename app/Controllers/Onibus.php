<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class Onibus extends ResourceController
{
    protected $format = 'json';

    // Aplica os cabeçalhos de CORS em todas as respostas da API
    public function __construct()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        
        // Trata requisições prévias do tipo OPTIONS feitas por navegadores/apps
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('HTTP/1.1 200 OK');
            exit();
        }
    }

    // GET /onibus
    public function index()
    {
        return $this->respond([
            'status'   => 'sucesso',
            'mensagem' => 'API de Lotações de BH ativa!'
        ], 200);
    }
 
    public function app() {
        return view('mapa');
    }

    // Função Haversine para cálculo de distância em metros
    private function calcularDistanciaEmMetros($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c);
    }

    // Formata o timestamp bruto (ex: 20261007203429) para horário legível (20:34:29)
    private function formatarHorarioLegivel($horarioBruto)
    {
        if (strlen($horarioBruto) === 14) {
            $hora = substr($horarioBruto, 8, 2);
            $minuto = substr($horarioBruto, 10, 2);
            $segundo = substr($horarioBruto, 12, 2);
            return "{$hora}:{$minuto}:{$segundo}";
        }
        return $horarioBruto;
    }

    // GET /onibus/proximos
    public function proximos()
    {
        // 1. Suporte a Transporte Suplementar via parâmetro ?tipo=suplementar ou ?tipo=sc
        $tipoParam = strtolower($this->request->getGet('tipo') ?? 'convencional');
        $paramApi = ($tipoParam === 'suplementar' || $tipoParam === 'sc') ? 'SC' : 'C';

        $url = 'https://temporeal.pbh.gov.br/?param=' . $paramApi;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

        $csvData = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($csvData === false || empty($csvData)) {
            return $this->failServerError('Erro ao conectar com a API de Tempo Real da PBH: ' . ($curlError ?: 'Resposta vazia'));
        }

        // Leitura dos parâmetros de busca
        $latRef = floatval($this->request->getGet('lat') ?? -19.9191);
        $lngRef = floatval($this->request->getGet('lng') ?? -43.9386);
        $raioMetros = floatval($this->request->getGet('raio_km') ?? 5) * 1000;
        $filtroLinha = trim($this->request->getGet('linha') ?? '');

        $csvData = str_replace("\r\n", "\n", trim($csvData));
        $linhas = explode("\n", $csvData);
        
        // Remove cabeçalho
        array_shift($linhas);

        $proximos = [];

        foreach ($linhas as $linha) {
            if (empty(trim($linha))) continue;

            $colunas = array_map('trim', explode(';', $linha));

            if (count($colunas) >= 10 && !empty($colunas[2]) && !empty($colunas[6])) {
                $lat = floatval(str_replace(',', '.', $colunas[2]));
                $lng = floatval(str_replace(',', '.', $colunas[3]));
                $vel = intval($colunas[5] ?? '0');
                $linhaOnibus = $colunas[6];
                $veiculo = $colunas[4] ?? 'N/I';
                $sentidoCod = $colunas[8] ?? '';
                $horarioBruto = $colunas[1] ?? '';

                if (!empty($filtroLinha) && $linhaOnibus !== $filtroLinha) {
                    continue;
                }

                $distancia = $this->calcularDistanciaEmMetros($latRef, $lngRef, $lat, $lng);

                if ($distancia <= $raioMetros) {
                    $velocidadeMinima = max($vel, 15);
                    $etaMinutos = ceil(($distancia / 1000) / $velocidadeMinima * 60);

                     $proximos[] = [
                        'veiculo'    => $veiculo,
                        'linha'      => $linhaOnibus,
                        'tipo'       => $paramApi === 'SC' ? 'suplementar' : 'convencional',
                        'sentido'    => $sentidoCod === '1' ? 'ida' : ($sentidoCod === '2' ? 'volta' : '?'),
                        'horario'    => $this->formatarHorarioLegivel($horarioBruto),
                        'lat'        => $lat, // <--- ADICIONADO PARA O MAPA
                        'lng'        => $lng, // <--- ADICIONADO PARA O MAPA
                        'dist_m'     => $distancia,
                        'vel_kmh'    => $vel,
                        'eta_min'    => $etaMinutos
                    ];
                }
            }
        }

        // Ordena por menor distância
        usort($proximos, fn($a, $b) => $a['dist_m'] <=> $b['dist_m']);

        $resultado = array_slice($proximos, 0, 10);

        return $this->respond([
            'status'  => 'sucesso',
            'sistema' => $paramApi === 'SC' ? 'transporte_suplementar' : 'transporte_convencional',
            'total'   => count($resultado),
            'dados'   => $resultado
        ], 200);
    }
}