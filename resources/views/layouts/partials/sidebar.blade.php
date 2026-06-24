        {{-- Sidebar Overlay --}}
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        {{-- Sidebar --}}
        <aside class="sidebar" id="sidebar" role="navigation" aria-label="Menu lateral">
            <div class="sidebar-header">
                <a href="{{ route('dashboard') }}" class="sidebar-logo">
                    <span class="sidebar-brand">POPRUA</span>
                    <span class="sidebar-version">v2.0</span>
                </a>
                <button type="button" class="sidebar-collapse-toggle" id="sidebar-collapse-toggle" title="Recolher menu">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                    </svg>
                </button>
            </div>

            <div class="sidebar-content">
                <nav class="sidebar-nav">
                    {{-- Operacional --}}
                    <div class="nav-section">
                        <span class="nav-section-title">Operacional</span>

                        <a href="{{ route('vistorias.index') }}" class="nav-item {{ request()->routeIs('vistorias.index') || (request()->routeIs('vistorias.*') && !request()->routeIs('vistorias.minhas')) ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            <span class="nav-item-text">Zeladorias</span>
                        </a>

                        <a href="{{ route('vistorias.minhas') }}" class="nav-item {{ request()->routeIs('vistorias.minhas') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                            <span class="nav-item-text">Minhas Zeladorias</span>
                        </a>

                        @can('create', App\Models\Vistoria::class)
                        <a href="{{ route('minha-equipe.index') }}" class="nav-item {{ request()->routeIs('minha-equipe.*') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <span class="nav-item-text">Minha Equipe</span>
                        </a>
                        @endcan

                        <a href="#" id="nav-sync-fotos" class="nav-item" x-on:click.prevent="syncAllPendingPhotos()">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span class="nav-item-text">Sincronizar Imagens</span>
                            <span id="sync-badge" class="hidden" style="background: var(--color-warning); color: #000; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 3px; margin-left: auto;">0</span>
                        </a>
                    </div>

                    {{-- Menu Principal --}}
                    <div class="nav-section">
                        <span class="nav-section-title">Menu</span>

                        <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            <span class="nav-item-text">Dashboard</span>
                        </a>

                        <a href="{{ route('mapa.index') }}" class="nav-item {{ request()->routeIs('mapa.*') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            <span class="nav-item-text">Mapa</span>
                        </a>

                        <a href="{{ route('pontos.index') }}" class="nav-item {{ request()->routeIs('pontos.*') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="nav-item-text">Gerenciar Pontos</span>
                        </a>

                        <a href="{{ route('moradores.index') }}" class="nav-item {{ request()->routeIs('moradores.*') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <span class="nav-item-text">Pessoas</span>
                        </a>
                    </div>

                    {{-- Relatorios (desktop only) --}}
                    <div class="nav-section hide-mobile">
                        <span class="nav-section-title">Relatorios</span>

                        <a href="{{ route('powerbi.index') }}" class="nav-item {{ request()->routeIs('powerbi.*') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span class="nav-item-text">Power BI</span>
                        </a>

                        <a href="{{ route('discussao.index') }}" target="_blank" class="nav-item {{ request()->routeIs('discussao.*') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            <span class="nav-item-text">Discussao</span>
                        </a>
                    </div>

                    {{-- Administracao --}}
                    @can('ver usuarios')
                    <div class="nav-section">
                        <span class="nav-section-title">Gestão de Acesso</span>

                        <a href="{{ route('admin.users.index') }}" class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <span class="nav-item-text">Usuários</span>
                        </a>

                        <a href="{{ route('admin.roles.index') }}" class="nav-item {{ request()->routeIs('admin.roles.*') || request()->routeIs('admin.permissions.*') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <span class="nav-item-text">Roles e Permissões</span>
                        </a>

                        <a href="{{ route('admin.matriz-permissoes') }}" class="nav-item {{ request()->routeIs('admin.matriz-permissoes') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M10 3v18M14 3v18"/>
                            </svg>
                            <span class="nav-item-text">Matriz de Permissões</span>
                        </a>
                    </div>

                    <div class="nav-section">
                        <span class="nav-section-title">Configuração</span>

                        <a href="{{ route('admin.parametros.index') }}" class="nav-item {{ request()->routeIs('admin.parametros.*') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                            </svg>
                            <span class="nav-item-text">Parametrização</span>
                        </a>

                        <a href="{{ route('minha-equipe.index') }}" class="nav-item {{ request()->routeIs('minha-equipe.*') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="nav-item-text">Minha Equipe</span>
                        </a>
                    </div>

                    <div class="nav-section">
                        <span class="nav-section-title">Sistema</span>

                        <a href="{{ route('admin.infraestrutura') }}" class="nav-item {{ request()->routeIs('admin.infraestrutura') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                            </svg>
                            <span class="nav-item-text">Infraestrutura</span>
                        </a>

                        <a href="{{ route('admin.sprint-11') }}" class="nav-item {{ request()->routeIs('admin.sprint-11') ? 'active' : '' }}">
                            <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                            </svg>
                            <span class="nav-item-text">Homologação</span>
                        </a>
                    </div>
                    @endcan
                </nav>
            </div>

            <div class="sidebar-footer">
                @auth
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                    <div class="sidebar-user-info">
                        <span class="sidebar-user-name">{{ Auth::user()->name }}</span>
                        <span class="sidebar-user-role">{{ Auth::user()->roles->first()?->name ?? 'Usuario' }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-icon" title="Sair">
                            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>
                </div>
                @endauth
            </div>
        </aside>
