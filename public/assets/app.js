let latRef = -19.9191; // Coordenada padrão (Praça Sete) caso o GPS negue
let lngRef = -43.9386;

// Inicializa o mapa sem zoom padrão pesado
const map = L.map('map', { zoomControl: false }).setView([latRef, lngRef], 14);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
}).addTo(map);

L.control.zoom({ position: 'topright' }).addTo(map);

// Ícone do Ponto de Embarque / Referência fixa
const stopIcon = L.divIcon({
    html: '<div class="stop-pin-icon">🚏</div>',
    className: 'stop-custom-icon',
    iconSize: [20, 20]
});

let pontoMarker = L.marker([latRef, lngRef], { icon: stopIcon }).addTo(map)
    .bindPopup("<b>Local de Referência</b>");

let marcadoresAtivos = {};
let linhasTrajeto = [];
let userMarker = null;

// Ícone customizado para a posição do utilizador (Ponto azul elegante)
const userIcon = L.divIcon({
    html: '<div style="background-color: #38bdf8; width: 16px; height: 16px; border-radius: 50%; border: 3px solid #ffffff; box-shadow: 0 0 10px rgba(56, 189, 248, 0.8);"></div>',
    className: 'user-gps-pin',
    iconSize: [16, 16]
});

// Função para atualizar ponto pelo select do painel
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
    if (loaderBox) loaderBox.classList.add('updating');

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

                const corLinha = bus.eta_min <= 3 ? '#10b981' : '#2563eb';
                desenharRotaReal(posLat, posLng, latRef, lngRef, corLinha);

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
        if (loaderBox) {
            setTimeout(() => {
                loaderBox.classList.remove('updating');
            }, 1000);
        }
    }
}

// 1. Tenta obter a localização GPS real do telemóvel imediatamente ao abrir
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
        (position) => {
            latRef = position.coords.latitude;
            lngRef = position.coords.longitude;

            map.setView([latRef, lngRef], 16);

            userMarker = L.marker([latRef, lngRef], { icon: userIcon }).addTo(map)
                .bindPopup("<b>Você está aqui</b>")
                .openPopup();

            pontoMarker.setLatLng([latRef, lngRef]);
            carregarOnibus();
        },
        (error) => {
            console.warn("Geolocalização recusada ou indisponível. A usar ponto padrão.", error);
            carregarOnibus();
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
} else {
    carregarOnibus();
}

// Atualização automática a cada 10 segundos
setInterval(carregarOnibus, 10000);

// Regista o Service Worker para habilitar o PWA
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/assets/sw.js')
        .then(() => console.log("Service Worker registado com sucesso!"))
        .catch((err) => console.log("Erro ao registar Service Worker:", err));
}