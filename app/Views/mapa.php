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

    <!-- CSS Externo Organizado -->
    <link rel="stylesheet" href="<?= base_url('assets/style.css') ?>">

    <!-- PWA Manifest & Tema -->
    <link rel="manifest" href="<?= base_url('assets/manifest.json') ?>">
    <meta name="theme-color" content="#0f172a">
</head>

<body>

    <select id="ponto-ref" onchange="atualizarPontoReferencia()">
    <option value="gps" selected>📍 Minha Localização Atual (GPS)</option>
    <option value="-19.9191,-43.9386">🚏 Praça Sete (Centro)</option>
    <option value="-19.8517,-43.9556">🚏 Estação Pampulha</option>
    <option value="-19.9383,-43.9331">🚏 Savassi</option>
    <option value="-19.9213,-43.9443">🚏 Mercado Central</option>
    </select>

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

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/assets/sw.js')
            .then(() => console.log("PWA Service Worker registado com sucesso!"))
            .catch((err) => console.error("Erro ao registar Service Worker:", err));
        }
    </script>
</body>
</html>