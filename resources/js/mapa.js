import L from './leaflet-setup';

function escapeHtml(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    const APP_BASE = window.APP_BASE;

    const BH_CENTER = [-19.9135, -43.9514];
    const DEFAULT_ZOOM = 12;
    const MIN_ZOOM_VISTORIA = 19;

    const urlParams = new URLSearchParams(window.location.search);
    const lat = urlParams.get('lat');
    const lng = urlParams.get('lng');
    const zoom = urlParams.get('zoom') ? parseInt(urlParams.get('zoom')) : DEFAULT_ZOOM;
    const pontoId = urlParams.get('ponto_id');
    const geocoded = urlParams.get('geocoded') === '1';
    const ajustarMode = urlParams.get('ajustar') === '1' && pontoId;
    const enderecoParam = urlParams.get('endereco');
    const referenciaParam = urlParams.get('referencia');

    const map = L.map('map', {
        zoomControl: true,
        attributionControl: false
    });

    const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    });

    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; Esri &mdash; Sources: Esri, DigitalGlobe, GeoEye, Earthstar Geographics',
        maxZoom: 19
    });

    satelliteLayer.addTo(map);
    let currentBaseLayer = satelliteLayer;

    let selectedPointMarker = null;
    let geocodeMarker = null;
    let geocodeMode = geocoded && pontoId;
    let currentGeocodeCoords = null;

    function updateGeocodeCoords(lat, lng) {
        currentGeocodeCoords = { lat, lng };
        const coordsEl = document.getElementById('geocode-coords');
        if (coordsEl) {
            coordsEl.textContent = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
        }
    }

    function setGeocodeMarker(lat, lng, isInitial = false) {
        if (geocodeMarker) {
            map.removeLayer(geocodeMarker);
        }

        const markerColor = isInitial ? '#eab308' : '#10b981';

        geocodeMarker = L.circleMarker([lat, lng], {
            radius: 14,
            fillColor: markerColor,
            color: '#fff',
            weight: 3,
            opacity: 1,
            fillOpacity: 1
        }).addTo(map);

        updateGeocodeCoords(lat, lng);
    }

    if (lat && lng) {
        const pointLat = parseFloat(lat);
        const pointLng = parseFloat(lng);
        map.setView([pointLat, pointLng], zoom);

        map.whenReady(() => {
            if (geocodeMode) {
                setGeocodeMarker(pointLat, pointLng, true);
            } else if (ajustarMode) {
                selectedPointMarker = L.circleMarker([pointLat, pointLng], {
                    radius: 14,
                    fillColor: '#f59e0b',
                    color: '#fff',
                    weight: 3,
                    opacity: 1,
                    fillOpacity: 0.9
                }).addTo(map);

                const btnNovaAcaoEl = document.getElementById('btn-nova-acao');
                if (btnNovaAcaoEl) btnNovaAcaoEl.style.display = 'none';

                fetch(`${APP_BASE}/api/pontos/${pontoId}`)
                    .then(r => r.json())
                    .then(data => {
                        const endEl = document.getElementById('ajustar-endereco');
                        const enderecoTexto = data.logradouro
                            ? `${data.tipo || ''} ${data.logradouro}, ${data.numero || 'S/N'}`
                            : 'Ponto #' + pontoId;
                        if (endEl) {
                            endEl.textContent = enderecoTexto;
                        }
                        selectedPointMarker.bindPopup(
                            `<div style="font-size: var(--text-sm);">` +
                            `<p style="font-weight: var(--font-semibold); color: var(--text-primary);">${escapeHtml(enderecoTexto)}</p>` +
                            (data.bairro ? `<p style="font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px;">${escapeHtml(data.bairro)}${data.resultado ? ' — ' + escapeHtml(data.resultado) : ''}</p>` : '') +
                            `<p class="text-mono" style="font-size: var(--text-xs); color: var(--text-muted); margin-top: 4px;">${pointLat.toFixed(6)}, ${pointLng.toFixed(6)}</p>` +
                            `</div>`,
                            { autoPan: false }
                        ).openPopup();
                    })
                    .catch(() => {});

                function updateAjustarCoords() {
                    const center = map.getCenter();
                    const coordsEl = document.getElementById('ajustar-coords');
                    if (coordsEl) {
                        coordsEl.textContent = `${center.lat.toFixed(6)}, ${center.lng.toFixed(6)}`;
                    }
                }
                updateAjustarCoords();
                map.on('moveend', updateAjustarCoords);

                const btnConfirmar = document.getElementById('btn-confirmar-ajuste');
                if (btnConfirmar) {
                    btnConfirmar.addEventListener('click', async function() {
                        const center = map.getCenter();
                        const btn = this;
                        btn.disabled = true;
                        btn.innerHTML = '<svg class="spinner" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24"><circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Salvando...';

                        try {
                            const response = await fetch(`${APP_BASE}/api/pontos/${pontoId}/coordenadas`, {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ lat: center.lat, lng: center.lng })
                            });

                            const data = await response.json();

                            if (data.success) {
                                window.location.href = `${APP_BASE}/pontos?ajuste_sucesso=1&ponto_endereco=${encodeURIComponent(data.endereco_ponto || '')}`;
                            } else {
                                showToast('Erro ao salvar: ' + (data.message || 'Erro desconhecido'), 'error');
                                btn.disabled = false;
                                btn.innerHTML = '<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Salvar';
                            }
                        } catch (error) {
                            console.error('Erro:', error);
                            showToast('Erro ao salvar coordenadas. Tente novamente.', 'error');
                            btn.disabled = false;
                            btn.innerHTML = '<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Salvar';
                        }
                    });
                }
            } else {
                selectedPointMarker = L.circleMarker([pointLat, pointLng], {
                    radius: 12,
                    fillColor: '#3b82f6',
                    color: '#fff',
                    weight: 3,
                    opacity: 1,
                    fillOpacity: 1
                }).addTo(map);

                let popupContent = '<div style="font-size: var(--text-sm);">';
                if (enderecoParam) {
                    popupContent += `<p style="font-weight: var(--font-semibold); color: var(--text-primary);">${escapeHtml(decodeURIComponent(enderecoParam))}</p>`;
                }
                if (referenciaParam) {
                    popupContent += `<p style="font-size: var(--text-xs); color: var(--text-secondary); margin-top: var(--space-1);"><strong>Ref:</strong> ${escapeHtml(decodeURIComponent(referenciaParam))}</p>`;
                }
                popupContent += `<p class="text-mono" style="font-size: var(--text-xs); color: var(--text-muted); margin-top: var(--space-1);">${pointLat.toFixed(6)}, ${pointLng.toFixed(6)}</p>`;
                popupContent += '</div>';

                selectedPointMarker.bindPopup(popupContent).openPopup();
            }
        });
    } else {
        map.setView(BH_CENTER, DEFAULT_ZOOM);
    }

    if (geocodeMode) {
        map.on('click', function(e) {
            setGeocodeMarker(e.latlng.lat, e.latlng.lng, false);
        });

        document.getElementById('btn-confirmar-geocode').addEventListener('click', async function() {
            if (!currentGeocodeCoords) return;

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<svg class="spinner" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24"><circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Salvando...';

            try {
                const response = await fetch(`${APP_BASE}/api/pontos/${pontoId}/coordenadas`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        lat: currentGeocodeCoords.lat,
                        lng: currentGeocodeCoords.lng
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showToast('Coordenadas salvas: ' + (data.endereco_ponto || 'ponto geocodificado'), 'success');
                    setTimeout(() => {
                        window.location.href = `${APP_BASE}/mapa?lat=${data.lat}&lng=${data.lng}&zoom=19`;
                    }, 800);
                } else {
                    showToast('Erro ao salvar: ' + (data.message || 'Erro desconhecido'), 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Confirmar';
                }
            } catch (error) {
                console.error('Erro:', error);
                showToast('Erro ao salvar coordenadas. Tente novamente.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Confirmar';
            }
        });
    }

    let markerClickedRecently = false;

    const markersLayer = L.markerClusterGroup({
        chunkedLoading: true,
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
        disableClusteringAtZoom: 18
    }).addTo(map);

    let regionaisLayer = null;
    let regionaisLabelsLayer = null;
    let bairrosLayer = null;
    let bairrosLabelsLayer = null;
    let limiteLayer = null;
    let allPointsLoaded = false;
    let allMarkers = [];

    const styles = {
        regionais: {
            color: '#3b82f6',
            weight: 2,
            opacity: 0.8,
            fillOpacity: 0.1
        },
        bairros: {
            color: '#22c55e',
            weight: 1,
            opacity: 0.6,
            fillOpacity: 0.05
        },
        limite: {
            color: '#ef4444',
            weight: 3,
            opacity: 1,
            fillOpacity: 0
        }
    };

    const coresResultado = {
        1: '#dc2626',
        2: '#f97316',
        3: '#1f2937',
        4: '#6b7280',
        5: '#3b82f6',
        6: '#10b981',
        null: '#a855f7'
    };

    const legendaResultado = {
        1: 'Fenômeno persiste',
        2: 'Impactado parcialmente',
        3: 'Deixou de Ocorrer',
        4: 'PSR ausente',
        5: 'Não constatado',
        6: 'Em Conformidade',
        null: 'Sem vistoria'
    };

    async function loadRegionais() {
        if (regionaisLayer) return;
        const response = await fetch(`${APP_BASE}/api/geo/regionais`);
        const data = await response.json();
        regionaisLayer = L.geoJSON(data, {
            style: styles.regionais,
            onEachFeature: (feature, layer) => {
                layer.bindTooltip(feature.properties.nome, {
                    permanent: false,
                    direction: 'center',
                    className: 'map-tooltip'
                });
            }
        });

        regionaisLabelsLayer = L.layerGroup();
        data.features.forEach(feature => {
            if (feature.properties && feature.properties.nome) {
                const bounds = L.geoJSON(feature).getBounds();
                const center = bounds.getCenter();

                const label = L.marker(center, {
                    icon: L.divIcon({
                        className: 'regional-label',
                        html: `<span class="map-label map-label-regional">${escapeHtml(feature.properties.nome)}</span>`,
                        iconSize: null,
                        iconAnchor: [0, 0]
                    })
                });
                regionaisLabelsLayer.addLayer(label);
            }
        });
    }

    function updateRegionaisLabels() {
        if (!regionaisLabelsLayer || !document.getElementById('layer-regionais').checked) return;

        const zoom = map.getZoom();
        if (zoom >= 12 && zoom < 15) {
            if (!map.hasLayer(regionaisLabelsLayer)) {
                regionaisLabelsLayer.addTo(map);
            }
        } else {
            if (map.hasLayer(regionaisLabelsLayer)) {
                map.removeLayer(regionaisLabelsLayer);
            }
        }
    }

    async function loadBairros() {
        if (bairrosLayer) return;
        const response = await fetch(`${APP_BASE}/api/geo/bairros`);
        const data = await response.json();
        bairrosLayer = L.geoJSON(data, {
            style: styles.bairros,
            onEachFeature: (feature, layer) => {
                layer.bindTooltip(feature.properties.nome, {
                    permanent: false,
                    direction: 'center',
                    className: 'map-tooltip map-tooltip-sm'
                });
            }
        });

        bairrosLabelsLayer = L.layerGroup();
        data.features.forEach(feature => {
            if (feature.properties && feature.properties.nome) {
                const bounds = L.geoJSON(feature).getBounds();
                const center = bounds.getCenter();

                const label = L.marker(center, {
                    icon: L.divIcon({
                        className: 'bairro-label',
                        html: `<span class="map-label map-label-bairro">${escapeHtml(feature.properties.nome)}</span>`,
                        iconSize: null,
                        iconAnchor: [0, 0]
                    })
                });
                bairrosLabelsLayer.addLayer(label);
            }
        });
    }

    function updateBairrosLabels() {
        if (!bairrosLabelsLayer || !document.getElementById('layer-bairros').checked) return;

        const zoom = map.getZoom();
        if (zoom >= 15) {
            if (!map.hasLayer(bairrosLabelsLayer)) {
                bairrosLabelsLayer.addTo(map);
            }
        } else {
            if (map.hasLayer(bairrosLabelsLayer)) {
                map.removeLayer(bairrosLabelsLayer);
            }
        }
    }

    map.on('zoomend', function() {
        updateRegionaisLabels();
        updateBairrosLabels();

        const zoom = map.getZoom();
        const radius = zoom >= 18 ? 14 : 8;
        const weight = zoom >= 18 ? 3 : 2;
        allMarkers.forEach(m => {
            m.setRadius(radius);
            m.setStyle({ weight });
        });
    });

    async function loadLimite() {
        if (limiteLayer) return;
        const response = await fetch(`${APP_BASE}/api/geo/limite-municipio`);
        const data = await response.json();
        limiteLayer = L.geoJSON(data, {
            style: styles.limite
        });
    }

    let loadPointsTimeout = null;
    let loadedBounds = null;

    function loadAllPoints() {
        if (!document.getElementById('layer-pontos').checked) return;

        const bounds = map.getBounds();
        const pad = 0.02;
        const params = new URLSearchParams({
            north: (bounds.getNorth() + pad).toFixed(6),
            south: (bounds.getSouth() - pad).toFixed(6),
            east: (bounds.getEast() + pad).toFixed(6),
            west: (bounds.getWest() - pad).toFixed(6)
        });

        if (loadedBounds && loadedBounds.contains(bounds)) return;

        fetch(`${APP_BASE}/api/pontos?${params}`)
            .then(response => response.json())
            .then(data => {
                console.debug("Pontos recebidos:", data.length);

                allMarkers = [];
                data.forEach(ponto => {
                    if (ponto.lat && ponto.lng) {
                        const lat = parseFloat(ponto.lat);
                        const lng = parseFloat(ponto.lng);
                        const resultadoId = ponto.resultado_acao_id;
                        const cor = coresResultado[resultadoId] || coresResultado[null];
                        const status = legendaResultado[resultadoId] || legendaResultado[null];

                        const marker = L.circleMarker([lat, lng], {
                            radius: 8,
                            fillColor: cor,
                            color: '#fff',
                            weight: 2,
                            opacity: 1,
                            fillOpacity: 0.9,
                            bubblingMouseEvents: false
                        }).bindPopup(function() {
                            const p = marker.pontoData;
                            const totalVistorias = p.total_vistorias || 0;
                            const complexidade = p.complexidade || 0;
                            const complexidadeCor = complexidade >= 8 ? '#dc2626' : complexidade >= 4 ? '#f59e0b' : '#6b7280';
                            const btnPonto = `<a href="${APP_BASE}/pontos/${p.id}" class="popup-btn popup-btn-primary">Relatório do ponto</a>`;
                            const btnVistoria = p.ultima_vistoria_id
                                ? `<a href="#" onclick="event.stopPropagation(); abrirRelatorio(${p.ultima_vistoria_id}); return false;" class="popup-btn popup-btn-secondary">Última vistoria</a>`
                                : '';
                            return `<strong>${escapeHtml(p.logradouro)}, ${escapeHtml(p.numero)}</strong><br>
                                <small>${escapeHtml(p.bairro)} - ${escapeHtml(p.regional)}</small><br>
                                <span style="color:${cor}; font-weight:bold;">● ${escapeHtml(status)}</span><br>
                                <span style="font-size: 11px; color: #6b7280;">Vistorias: <strong>${totalVistorias}</strong> | Complexidade: <strong style="color:${complexidadeCor}">${complexidade}</strong></span>
                                <div class="popup-actions">${btnPonto}${btnVistoria}</div>`;
                        });
                        marker.pontoData = ponto;

                        marker.on('click', function(e) {
                            L.DomEvent.stopPropagation(e);
                            markerClickedRecently = true;
                            setTimeout(() => { markerClickedRecently = false; }, 300);
                            map.flyTo(e.latlng, 18);
                        });
                        marker.resultadoId = resultadoId;
                        allMarkers.push(marker);
                    }
                });

                applyFilters();
                loadedBounds = map.getBounds().pad(0.3);
                allPointsLoaded = true;
                checkCrosshairOverPoint();
            })
            .catch(err => console.error('Erro ao carregar pontos:', err));
    }

    map.on('moveend', function() {
        if (loadedBounds && loadedBounds.contains(map.getBounds())) return;
        allPointsLoaded = false;
        clearTimeout(loadPointsTimeout);
        loadPointsTimeout = setTimeout(loadAllPoints, 400);
    });

    function applyFilters() {
        const activeFilters = new Set();
        document.querySelectorAll('.filter-resultado:checked').forEach(cb => {
            const val = cb.dataset.resultado;
            activeFilters.add(val === 'null' ? null : parseInt(val));
        });

        markersLayer.clearLayers();
        const filteredMarkers = allMarkers.filter(marker => activeFilters.has(marker.resultadoId));
        markersLayer.addLayers(filteredMarkers);
        console.debug("Markers visíveis:", filteredMarkers.length);
    }

    document.querySelectorAll('.filter-resultado').forEach(cb => {
        cb.addEventListener('change', applyFilters);
    });

    if (!ajustarMode) {
        loadAllPoints();
    }

    loadLimite().then(() => {
        if (document.getElementById('layer-limite').checked && limiteLayer) {
            limiteLayer.addTo(map);
        }
    });

    const layersPanel = document.getElementById('layers-panel');

    document.getElementById('btn-menu').addEventListener('click', () => {
        layersPanel.classList.toggle('hidden');
    });

    document.getElementById('layers-panel-close').addEventListener('click', () => {
        layersPanel.classList.add('hidden');
    });

    document.getElementById('base-street').addEventListener('change', function() {
        if (this.checked) {
            map.removeLayer(currentBaseLayer);
            streetLayer.addTo(map);
            currentBaseLayer = streetLayer;
        }
    });

    document.getElementById('base-satellite').addEventListener('change', function() {
        if (this.checked) {
            map.removeLayer(currentBaseLayer);
            satelliteLayer.addTo(map);
            currentBaseLayer = satelliteLayer;
        }
    });

    document.getElementById('layer-regionais').addEventListener('change', async function() {
        if (this.checked) {
            await loadRegionais();
            regionaisLayer.addTo(map);
            updateRegionaisLabels();
        } else {
            if (regionaisLayer) {
                map.removeLayer(regionaisLayer);
            }
            if (regionaisLabelsLayer) {
                map.removeLayer(regionaisLabelsLayer);
            }
        }
    });

    document.getElementById('layer-bairros').addEventListener('change', async function() {
        if (this.checked) {
            await loadBairros();
            bairrosLayer.addTo(map);
            updateBairrosLabels();
        } else {
            if (bairrosLayer) {
                map.removeLayer(bairrosLayer);
            }
            if (bairrosLabelsLayer) {
                map.removeLayer(bairrosLabelsLayer);
            }
        }
    });

    document.getElementById('layer-limite').addEventListener('change', async function() {
        if (this.checked) {
            await loadLimite();
            limiteLayer.addTo(map);
        } else if (limiteLayer) {
            map.removeLayer(limiteLayer);
        }
    });

    document.getElementById('layer-pontos').addEventListener('change', function() {
        if (this.checked) {
            markersLayer.addTo(map);
            if (!allPointsLoaded) {
                loadAllPoints();
            }
        } else {
            map.removeLayer(markersLayer);
        }
    });

    markersLayer.on('click', function(e) {
        const marker = e.layer;
        if (marker && marker.pontoData) {
            markerClickedRecently = true;
            setTimeout(() => { markerClickedRecently = false; }, 300);
            map.flyTo(e.latlng, 18);
        }
    });

    document.getElementById('btn-my-location').addEventListener('click', function() {
        const btn = this;
        const icon = document.getElementById('location-icon');
        const loader = document.getElementById('location-loader');

        const restoreButton = () => {
            if (loader) loader.classList.add('hidden');
            if (icon) icon.classList.remove('hidden');
            btn.classList.remove('active');
            btn.style.pointerEvents = 'auto';
        };

        if (icon) icon.classList.add('hidden');
        if (loader) loader.classList.remove('hidden');
        btn.classList.add('active');
        btn.style.pointerEvents = 'none';

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    map.flyTo([lat, lng], 18);
                    restoreButton();
                },
                error => {
                    restoreButton();
                    showToast('Nao foi possivel obter sua localizacao. Verifique as permissoes do navegador.', 'warning');
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        } else {
            restoreButton();
            showToast('Geolocalizacao nao suportada neste navegador.', 'warning');
        }
    });

    map.on('click', function(e) {
        if (markerClickedRecently || ajustarMode) return;
        map.flyTo(e.latlng, 18);
    });

    let crosshairFetchController = null;
    async function updateCrosshairAddress() {
        const center = map.getCenter();
        const lat = center.lat;
        const lng = center.lng;
        const zoom = map.getZoom();

        if (zoom < 16) {
            currentCrosshairEndereco = null;
            return;
        }

        if (crosshairFetchController) crosshairFetchController.abort();
        crosshairFetchController = new AbortController();

        try {
            const response = await fetch(`${APP_BASE}/api/enderecos/por-coordenadas?lat=${lat}&lng=${lng}`, {
                signal: crosshairFetchController.signal
            });
            const data = await response.json();

            if (data.encontrado) {
                const end = data.endereco;
                const vistoriaParams = new URLSearchParams({
                    lat, lng,
                    endereco_tipo: end.tipo || '',
                    endereco_logradouro: end.logradouro || '',
                    endereco_numero: Math.round(end.numero) || '',
                    endereco_bairro: end.bairro || '',
                    endereco_regional: end.regional || '',
                    endereco_distancia: data.distancia_metros
                });
                currentCrosshairEndereco = { lat, lng, end, distancia: data.distancia_metros, vistoriaParams };
            } else {
                currentCrosshairEndereco = null;
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.error('Erro ao buscar endereço:', err);
            }
        }
    }

    map.on('moveend', updateCrosshairAddress);

    let currentCrosshairEndereco = null;
    let crosshairPopupMarker = null;
    let crosshairPopup = null;

    function checkCrosshairOverPoint() {
        if (selectedPointMarker || geocodeMode || ajustarMode) return;
        const zoom = map.getZoom();
        if (zoom < 18) {
            if (crosshairPopup) {
                map.closePopup(crosshairPopup);
                crosshairPopup = null;
                crosshairPopupMarker = null;
            }
            return;
        }

        const center = map.getCenter();
        const centerPoint = map.latLngToContainerPoint(center);
        const threshold = 25;
        let closest = null;
        let closestDist = Infinity;

        allMarkers.forEach(marker => {
            const latlng = marker.getLatLng();
            if (!map.getBounds().contains(latlng)) return;
            const markerPoint = map.latLngToContainerPoint(latlng);
            const dx = centerPoint.x - markerPoint.x;
            const dy = centerPoint.y - markerPoint.y;
            const dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < threshold && dist < closestDist) {
                closest = marker;
                closestDist = dist;
            }
        });

        if (closest && closest !== crosshairPopupMarker) {
            if (crosshairPopup) map.closePopup(crosshairPopup);
            const p = closest.pontoData;
            const cor = coresResultado[p.resultado_acao_id] || coresResultado[null];
            const status = legendaResultado[p.resultado_acao_id] || legendaResultado[null];
            const totalVistorias = p.total_vistorias || 0;
            const complexidade = p.complexidade || 0;
            const complexidadeCor = complexidade >= 8 ? '#dc2626' : complexidade >= 4 ? '#f59e0b' : '#6b7280';
            const btnPonto = `<a href="${APP_BASE}/pontos/${p.id}" class="popup-btn popup-btn-primary">Relatório do ponto</a>`;
            const btnVistoria = p.ultima_vistoria_id
                ? `<a href="#" onclick="event.stopPropagation(); abrirRelatorio(${p.ultima_vistoria_id}); return false;" class="popup-btn popup-btn-secondary">Última vistoria</a>`
                : '';
            const content = `<strong>${escapeHtml(p.logradouro)}, ${escapeHtml(p.numero)}</strong><br>
                <small>${escapeHtml(p.bairro)} - ${escapeHtml(p.regional)}</small><br>
                <span style="color:${cor}; font-weight:bold;">● ${escapeHtml(status)}</span><br>
                <span style="font-size: 11px; color: #6b7280;">Vistorias: <strong>${totalVistorias}</strong> | Complexidade: <strong style="color:${complexidadeCor}">${complexidade}</strong></span>
                <div class="popup-actions">${btnPonto}${btnVistoria}</div>`;

            crosshairPopup = L.popup({ closeButton: false, offset: [0, -10] })
                .setLatLng(closest.getLatLng())
                .setContent(content)
                .openOn(map);
            crosshairPopupMarker = closest;
        } else if (!closest && crosshairPopupMarker) {
            if (crosshairPopup) map.closePopup(crosshairPopup);
            crosshairPopup = null;
            crosshairPopupMarker = null;
        }
    }

    map.on('moveend', checkCrosshairOverPoint);
    map.on('zoomend', checkCrosshairOverPoint);

    const btnNovaAcao = document.getElementById('btn-nova-acao');
    const MIN_ZOOM_NOVA_ACAO = 17;

    function updateBtnNovaAcao() {
        const visible = map.getZoom() >= MIN_ZOOM_NOVA_ACAO;
        btnNovaAcao.style.display = visible ? 'flex' : 'none';
    }
    map.on('zoomend', updateBtnNovaAcao);
    updateBtnNovaAcao();

    btnNovaAcao.addEventListener('click', function() {
        if (currentCrosshairEndereco) {
            window.location.href = `${APP_BASE}/vistorias/create?${currentCrosshairEndereco.vistoriaParams.toString()}`;
        } else {
            const center = map.getCenter();
            const params = new URLSearchParams({ lat: center.lat, lng: center.lng });
            window.location.href = `${APP_BASE}/vistorias/create?${params.toString()}`;
        }
    });

    function abrirRelatorio(vistoriaId) {
        const modal = document.getElementById('relatorio-modal');
        const iframe = document.getElementById('relatorio-iframe');
        const loader = document.getElementById('relatorio-loader');

        modal.classList.remove('hidden');
        loader.classList.remove('hidden');
        iframe.classList.add('hidden');

        iframe.src = `${APP_BASE}/vistorias/${vistoriaId}/relatorio`;

        iframe.onload = function() {
            loader.classList.add('hidden');
            iframe.classList.remove('hidden');
        };

        document.body.style.overflow = 'hidden';
    }

    function fecharRelatorio() {
        const modal = document.getElementById('relatorio-modal');
        const iframe = document.getElementById('relatorio-iframe');

        modal.classList.add('hidden');
        iframe.src = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            fecharRelatorio();
        }
    });

    window.abrirRelatorio = abrirRelatorio;
    window.fecharRelatorio = fecharRelatorio;

    // ========== BUSCA DE ENDERECO ==========
    const searchInput = document.getElementById('search-endereco');
    const searchResults = document.getElementById('search-results');
    let searchMarker = null;
    let searchTimeout = null;
    let selectedLogradouro = null;

    if (searchInput) {
        searchInput.addEventListener('click', e => e.stopPropagation());
    }

    function parseEnderecoInput(valor) {
        const trimmed = valor.trim();
        const matchVirgula = trimmed.match(/^(.+?),\s*(\d+)\s*(?:-.*)?$/);
        if (matchVirgula) {
            return { texto: matchVirgula[1].trim(), numero: parseInt(matchVirgula[2]) };
        }
        const matchVirgulaSemNum = trimmed.match(/^(.+?),\s*(?:-.*)?$/);
        if (matchVirgulaSemNum) {
            return { texto: matchVirgulaSemNum[1].trim(), numero: null };
        }
        const match = trimmed.match(/^(.+?)[\s]+(\d+)\s*$/);
        if (match) {
            return { texto: match[1].trim(), numero: parseInt(match[2]) };
        }
        return { texto: trimmed, numero: null };
    }

    function extrairLogradouro(texto) {
        return texto.replace(/^(AVE|RUA|PCA|ALA|TRV|BEC|PRC|VIA|ROD|EST|LAD)\s+/i, '');
    }

    if (searchInput && searchResults) {
        searchInput.addEventListener('input', function(e) {
            e.stopPropagation();

            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            if (selectedLogradouro) {
                const prefixo = `${selectedLogradouro.tipo} ${selectedLogradouro.logradouro},`;
                if (!this.value.startsWith(prefixo)) {
                    selectedLogradouro = null;
                } else {
                    searchResults.classList.add('hidden');
                    return;
                }
            }

            const { texto, numero } = parseEnderecoInput(this.value);
            if (texto.length < 2) {
                searchResults.classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(() => {
                buscarLogradouros(texto, numero);
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });
    }

    async function buscarLogradouros(termo, numero = null) {
        try {
            searchResults.innerHTML = '<div style="padding: var(--space-2); text-align: center;">Buscando...</div>';
            searchResults.classList.remove('hidden');

            const termoBusca = extrairLogradouro(termo);

            let url = `${APP_BASE}/api/enderecos/logradouros?q=${encodeURIComponent(termoBusca)}`;
            if (numero) {
                url += `&numero=${numero}`;
            }

            const response = await fetch(url);
            const resultados = await response.json();

            if (resultados.length === 0) {
                searchResults.innerHTML = '<div style="padding: var(--space-2); text-align: center; color: var(--text-muted);">Nenhum logradouro encontrado</div>';
            } else {
                searchResults.innerHTML = resultados.map(item => {
                    const tipo = item.tipo || '';
                    const logr = item.logradouro || '';
                    const reg = item.regional || '';
                    const num = item.numero || '';

                    const label = num
                        ? `${tipo} ${logr}, ${num} - ${reg}`
                        : `${tipo} ${logr} - ${reg}`;

                    return `
                        <button type="button" class="autocomplete-item" data-tipo="${escapeHtml(tipo)}" data-logradouro="${escapeHtml(logr)}" data-regional="${escapeHtml(reg)}" data-numero="${escapeHtml(num)}">
                            <div style="font-weight: var(--font-medium);">${escapeHtml(label)}</div>
                        </button>
                    `;
                }).join('');

                searchResults.querySelectorAll('.autocomplete-item').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const tipo = this.dataset.tipo;
                        const logradouro = this.dataset.logradouro;
                        const regional = this.dataset.regional;
                        const num = this.dataset.numero;
                        selectedLogradouro = { tipo, logradouro, regional };

                        if (num) {
                            searchInput.value = `${tipo} ${logradouro}, ${num} - ${regional}`;
                            searchResults.classList.add('hidden');
                            buscarEnderecoCompleto();
                        } else {
                            searchInput.value = `${tipo} ${logradouro}, `;
                            searchResults.classList.add('hidden');
                            searchInput.focus();
                        }
                    });
                });
            }
        } catch (err) {
            console.error('Erro na busca:', err);
            searchResults.innerHTML = '<div style="padding: var(--space-2); text-align: center; color: var(--color-danger);">Erro ao buscar</div>';
        }
    }

    async function buscarEnderecoCompleto() {
        const { texto, numero } = parseEnderecoInput(searchInput.value);
        if (!texto) {
            searchInput.focus();
            return;
        }

        const logradouro = selectedLogradouro
            ? selectedLogradouro.logradouro
            : extrairLogradouro(texto);

        try {
            const params = new URLSearchParams({ logradouro });
            if (numero && numero > 0) {
                params.append('numero', numero);
            }

            const response = await fetch(`${APP_BASE}/api/enderecos/buscar?${params}`);
            const result = await response.json();

            if (result.encontrado) {
                const end = result.endereco;
                const lat = parseFloat(end.lat);
                const lng = parseFloat(end.lng);

                const numeroInformado = result.numero_informado;
                const isAproximado = !result.exato || !numeroInformado;
                const numLabel = Math.round(end.numero);
                const enderecoLabel = `${end.tipo} ${end.logradouro}, ${numLabel} - ${end.regional}`;

                searchInput.value = enderecoLabel;

                let mensagemAproximado = null;
                if (!numeroInformado) {
                    mensagemAproximado = 'centro';
                } else if (!result.exato) {
                    mensagemAproximado = numero;
                }

                irParaEndereco(lat, lng, enderecoLabel, end.bairro, end.regional, isAproximado, mensagemAproximado);
            } else {
                showToast('Endereco nao encontrado', 'warning');
            }
        } catch (err) {
            console.error('Erro na busca:', err);
            showToast('Erro ao buscar endereco', 'error');
        }
    }

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchResults.classList.add('hidden');
            buscarEnderecoCompleto();
        }
    });

    function irParaEndereco(lat, lng, endereco, bairro, regional, isAproximado = false, mensagemAproximado = null) {
        if (searchMarker) {
            map.removeLayer(searchMarker);
            searchMarker = null;
        }

        map.flyTo([lat, lng], 18);

        selectedLogradouro = null;
    }
});
