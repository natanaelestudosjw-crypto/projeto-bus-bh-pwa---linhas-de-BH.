<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lotações BH — Tempo Real</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        :root {
            --panel-bg: rgba(15, 23, 42, 0.92);
            --panel-border: rgba(255, 255, 255, 0.12);
            --primary: #38bdf8;
            --suplementar: #fbbf24;
            --success: #34d399;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --card-bg: rgba(30, 41, 59, 0.7);
            --card-hover: rgba(51, 65, 85, 0.9);
            --radius-lg: 20px;
            --radius-md: 12px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        html, body {
            height: 100%;
            width: 100%;
            overflow: hidden;
            background-color: #0f172a;
            color: var(--text-main);
        }

        #map {
            height: 100vh;
            width: 100vw;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }

        /* Painel Flutuante estilo App Mobile */
        .app-panel {
            position: absolute;
            top: 16px;
            left: 16px;
            z-index: 1000;
            width: calc(100% - 32px);
            max-width: 380px;
            background: var(--panel-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--panel-border);
            border-radius: var(--radius-lg);
            padding: 16px;
            box-shadow: 0 20px 30px rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .brand-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .live-indicator {
            width: 10px;
            height: 10px;
            background-color: var(--success);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--success);
            animation: radarPulse 2s infinite;
        }

        @keyframes radarPulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(52, 211, 153, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0); }
        }

        .brand-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.02em;
        }

        /* Microanimação do Carregador */
        .loader-hub {
            position: relative;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .spinner-ring {
            width: 22px;
            height: 22px;
            border: 2.5px solid rgba(255, 255, 255, 0.15);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            transition: all 0.3s ease;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .bus-drift-icon {
            position: absolute;
            font-size: 18px;
            opacity: 0;
            transform: scale(0.3) translateX(-10px);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            pointer-events: none;
        }

        .smoke-particle {
            position: absolute;
            left: 2px;
            top: 14px;
            width: 5px;
            height: 5px;
            background: rgba(226, 232, 240, 0.8);
            border-radius: 50%;
            opacity: 0;
            pointer-events: none;
        }

        .loader-hub.updating .spinner-ring {
            opacity: 0;
            transform: scale(0.2) rotate(180deg);
        }

        .loader-hub.updating .bus-drift-icon {
            opacity: 1;
            transform: scale(1.1) translateX(2px);
            animation: busVibrate 0.12s infinite alternate;
        }

        .loader-hub.updating .smoke-particle {
            animation: smokePuff 0.6s ease-out infinite;
        }

        @keyframes busVibrate {
            0% { transform: scale(1.1) translateY(0) translateX(2px); }
            100% { transform: scale(1.1) translateY(-1.5px) translateX(4px); }
        }

        @keyframes smokePuff {
            0% { opacity: 0.9; transform: translate(0, 0) scale(0.8); }
            100% { opacity: 0; transform: translate(-14px, -6px) scale(2.2); }
        }

        /* Controles de Busca */
        .controls-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .input-group label {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        select, input {
            width: 100%;
            background: rgba(30, 41, 59, 0.85);
            border: 1px solid var(--panel-border);
            color: var(--text-main);
            padding: 9px 12px;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 500;
            outline: none;
        }

        .row-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        /* Lista de Veículos */
        .bus-scroller {
            max-height: 200px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 12px;
            padding-right: 2px;
        }

        .bus-scroller::-webkit-scrollbar {
            width: 4px;
        }
        .bus-scroller::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        .bus-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-left: 4px solid var(--primary);
            padding: 10px 12px;
            border-radius: var(--radius-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .bus-card:hover {
            background: var(--card-hover);
            transform: translateX(2px);
        }

        .bus-card.suplementar {
            border-left-color: var(--suplementar);
        }

        .bus-info .line-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
        }

        .bus-info .details {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .bus-eta-box {
            text-align: right;
        }

        .bus-eta-box .eta-time {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--success);
        }

        .bus-eta-box .distance {
            font-size: 0.72rem;
            color: var(--text-muted);
        }

        /* Marcadores Visíveis no Mapa */
        .map-pin-badge {
            background: #2563eb;
            color: #ffffff;
            border: 2px solid #ffffff;
            border-radius: 16px;
            padding: 3px 8px;
            font-weight: 800;
            font-size: 11px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .map-pin-badge.suplementar {
            background: #d97706;
        }

        .stop-pin-icon {
            background: #ef4444;
            color: #fff;
            border: 2px solid #fff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.5);
        }
    </style>
</head>
<body>

    <div class="app-panel">
        <div class="panel-header">
            <div class="brand-group">
                <span class="live-indicator"></span>
                <h1 class="brand-title">Lotações BH</h1>
            </div>
            
            <div class="loader-hub" id="loader-box">
                <div class="spinner-ring"></div>
                <div class="smoke-particle"></div>
                <div class="bus-drift-icon">🚌</div>
            </div>
        </div>

        <div class="controls-grid">
            <div class="input-group">
                <label>Ponto/Estação de Embarque</label>
                <select id="ponto-ref" onchange="atualizarPontoReferencia()">
                    <option value="-19.9191,-43.9386" selected>🚏 Praça Sete (Centro)</option>
                    <option value="-19.8517,-43.9556">🚏 Estação Pampulha</option>
                    <option value="-19.9383,-43.9331">🚏 Savassi (Praça Diogo de Vasconcelos)</option>
                    <option value="-19.9213,-43.9443">🚏 Mercado Central</option>
                    <option value="-19.8021,-43.9482">🚏 Estação Vilarinho (Venda Nova)</option>
                    <option value="-19.9754,-43.9442">🚏 BH Shopping (Belvedere)</option>
                    <option value="-19.9231,-43.9912">🚏 PUC Minas (Coração Eucarístico)</option>
                </select>
            </div>

            <div class="row-split">
                <div class="input-group">
                    <label>Tipo</label>
                    <select id="tipo-feed" onchange="carregarOnibus()">
                        <option value="convencional">Convencional</option>
                        <option value="suplementar">Suplementar</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Filtrar Linha</label>
                    <input type="text" id="filtro-linha" placeholder="Ex: 62, 5106" onkeyup="carregarOnibus()">
                </div>
            </div>
        </div>

        <div class="bus-scroller" id="bus-list">
            <div style="text-align: center; color: #64748b; font-size: 0.8rem; padding: 12px;">
                Buscando ônibus próximos...
            </div>
        </div>
    </div>

    <div id="map"></div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let latRef = -19.9191;
        let lngRef = -43.9386;

        const map = L.map('map', { zoomControl: false }).setView([latRef, lngRef], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        L.control.zoom({ position: 'topright' }).addTo(map);

        // Ícone do Ponto de Embarque
        const stopIcon = L.divIcon({
            html: '<div class="stop-pin-icon">🚏</div>',
            className: 'stop-custom-icon',
            iconSize: [20, 20]
        });

        let pontoMarker = L.marker([latRef, lngRef], { icon: stopIcon }).addTo(map)
            .bindPopup("<b>Ponto de Embarque Selecionado</b>")
            .openPopup();

        let marcadoresAtivos = {};
        let linhasTrajeto = [];

        function atualizarPontoReferencia() {
            const valor = document.getElementById('ponto-ref').value;
            const partes = valor.split(',');
            latRef = parseFloat(partes[0]);
            lngRef = parseFloat(partes[1]);

            map.setView([latRef, lngRef], 15);
            pontoMarker.setLatLng([latRef, lngRef]);
            carregarOnibus();
        }

        // Função para buscar rota real pelas vias via OSRM
        async function desenharRotaReal(latOrigem, lngOrigem, latDestino, lngDestino, cor) {
            try {
                const url = `https://router.project-osrm.org/route/v1/driving/${lngOrigem},${latOrigem};${lngDestino},${latDestino}?overview=full&geometries=geojson`;
                const resp = await fetch(url);
                const json = await resp.json();

                if (json.routes && json.routes.length > 0) {
                    const coords = json.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
                    const polyline = L.polyline(coords, {
                        color: cor,
                        weight: 4,
                        opacity: 0.8
                    }).addTo(map);
                    linhasTrajeto.push(polyline);
                }
            } catch (e) {
                // Fallback em linha reta caso o OSRM atinja limite
                const fallbackLine = L.polyline([[latOrigem, lngOrigem], [latDestino, lngDestino]], {
                    color: cor,
                    weight: 2,
                    dashArray: '5, 5',
                    opacity: 0.6
                }).addTo(map);
                linhasTrajeto.push(fallbackLine);
            }
        }

        async function carregarOnibus() {
            const loaderBox = document.getElementById('loader-box');
            loaderBox.classList.add('updating');

            const tipo = document.getElementById('tipo-feed').value;
            const filtroLinha = document.getElementById('filtro-linha').value.trim().toUpperCase();
            const containerLista = document.getElementById('bus-list');

            try {
                const response = await fetch(`/onibus/proximos?lat=${latRef}&lng=${lngRef}&raio_km=5&tipo=${tipo}`);
                const data = await response.json();

                // Limpa trajetos anteriores
                linhasTrajeto.forEach(l => map.removeLayer(l));
                linhasTrajeto = [];

                if (data.status === 'sucesso' && data.dados.length > 0) {
                    containerLista.innerHTML = '';
                    const veiculosAtuais = new Set();

                    // Filtra por linha digitada, se houver
                    const listaFiltrada = data.dados.filter(b => !filtroLinha || b.linha.toUpperCase().includes(filtroLinha));

                    if (listaFiltrada.length === 0) {
                        containerLista.innerHTML = '<div style="text-align: center; color: #64748b; padding: 12px;">Nenhum ônibus desta linha próximo.</div>';
                    }

                    listaFiltrada.forEach((bus) => {
                        const isSuplementar = bus.tipo === 'suplementar';
                        const idVeiculo = bus.veiculo;
                        veiculosAtuais.add(idVeiculo);

                        const posLat = bus.lat;
                        const posLng = bus.lng;

                        // Traça rota real pelas vias
                        const corLinha = bus.eta_min <= 3 ? '#10b981' : '#2563eb';
                        desenharRotaReal(posLat, posLng, latRef, lngRef, corLinha);

                        // Marcador do Ônibus
                        if (marcadoresAtivos[idVeiculo]) {
                            marcadoresAtivos[idVeiculo].setLatLng([posLat, posLng]);
                        } else {
                            const iconHtml = `<div class="map-pin-badge ${isSuplementar ? 'suplementar' : ''}">🚌 ${bus.linha}</div>`;
                            const customIcon = L.divIcon({
                                html: iconHtml,
                                className: 'custom-bus-pin',
                                iconSize: [60, 24]
                            });

                            const marker = L.marker([posLat, posLng], { icon: customIcon }).addTo(map)
                                .bindPopup(`<b>Linha ${bus.linha}</b><br>Veículo: ${bus.veiculo}<br>Velocidade: ${bus.vel_kmh} km/h<br>ETA: ~${bus.eta_min} min`);
                            
                            marcadoresAtivos[idVeiculo] = marker;
                        }

                        // Card da lista
                        const card = document.createElement('div');
                        card.className = `bus-card ${isSuplementar ? 'suplementar' : ''}`;
                        card.innerHTML = `
                            <div class="bus-info">
                                <div class="line-title">Linha ${bus.linha}</div>
                                <div class="details">Veículo ${bus.veiculo} • ${bus.vel_kmh} km/h</div>
                            </div>
                            <div class="bus-eta-box">
                                <div class="eta-time">~${bus.eta_min} min</div>
                                <div class="distance">${bus.dist_m}m</div>
                            </div>
                        `;

                        card.onclick = () => {
                            map.setView([posLat, posLng], 16, { animate: true });
                            marcadoresAtivos[idVeiculo].openPopup();
                        };

                        containerLista.appendChild(card);
                    });

                    // Remove veículos antigos
                    Object.keys(marcadoresAtivos).forEach(id => {
                        if (!veiculosAtuais.has(id)) {
                            map.removeLayer(marcadoresAtivos[id]);
                            delete marcadoresAtivos[id];
                        }
                    });

                } else {
                    containerLista.innerHTML = '<div style="text-align: center; color: #64748b; padding: 12px;">Nenhum veículo encontrado no raio de 5km.</div>';
                }
            } catch (err) {
                console.error("Erro ao carregar dados:", err);
            } finally {
                setTimeout(() => {
                    loaderBox.classList.remove('updating');
                }, 1000);
            }
        }

        setInterval(carregarOnibus, 10000);
        carregarOnibus();
    </script>
</body>
</html>