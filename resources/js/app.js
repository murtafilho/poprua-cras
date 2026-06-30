import './bootstrap';
import './debug-panel';
import {
    cleanupOrphanedTempRecords,
    countSyncablePhotos,
    getSyncablePhotos,
    openFotosDB,
    removePendingPhotoById,
    uploadPendingPhoto,
} from './offline-upload';

// Alpine.js (usado pelo Breeze)
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

// Dark Mode Toggle
function toggleDarkMode() {
    const html = document.documentElement;
    const isDark = html.classList.contains('dark');
    
    if (isDark) {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    } else {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    }
    
    // Atualizar ícone do botão se existir
    updateDarkModeIcon();
}

// Exportar para window para uso global (disponível imediatamente)
window.toggleDarkMode = toggleDarkMode;

window.getDarkMode = function() {
    return document.documentElement.classList.contains('dark');
};

function updateDarkModeIcon() {
    const isDark = window.getDarkMode();
    const darkIcons = document.querySelectorAll('[data-dark-icon]');
    const lightIcons = document.querySelectorAll('[data-light-icon]');
    
    darkIcons.forEach(icon => {
        if (isDark) {
            icon.classList.remove('hidden');
        } else {
            icon.classList.add('hidden');
        }
    });
    
    lightIcons.forEach(icon => {
        if (isDark) {
            icon.classList.add('hidden');
        } else {
            icon.classList.remove('hidden');
        }
    });
}

// Toast helper
window.showToast = function(message, type = 'info', duration = 3000) {
    const toast = document.getElementById('app-toast');
    if (!toast) return;
    toast.textContent = message;
    toast.className = 'toast toast-' + type + ' show';
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => { toast.classList.remove('show'); }, duration);
};

document.addEventListener('DOMContentLoaded', function() {
    updateDarkModeIcon();
    window.toggleDarkMode = toggleDarkMode;

    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const collapseToggle = document.getElementById('sidebar-collapse-toggle');
    const bottomNavMore = document.getElementById('bottom-nav-more');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
        if (bottomNavMore) bottomNavMore.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        if (bottomNavMore) bottomNavMore.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    if (bottomNavMore) {
        bottomNavMore.addEventListener('click', function() {
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        });
    }

    if (overlay) overlay.addEventListener('click', closeSidebar);

    if (collapseToggle) {
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
        collapseToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024 && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

    // Android back button guard
    if (window.history.length <= 1) {
        window.history.pushState({ poprua: true }, '');
    }
    window.addEventListener('popstate', function() {
        window.history.pushState({ poprua: true }, '');
        if (confirm('Deseja sair do aplicativo?')) {
            window.history.go(-2);
        }
    });

    // Photo sync badge
    const APP_BASE = document.querySelector('meta[name="app-base"]')?.content || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function updateSyncBadge() {
        const count = await countSyncablePhotos();
        const badge = document.getElementById('sync-badge');
        if (badge) {
            badge.textContent = count;
            badge.classList.toggle('hidden', count === 0);
        }
    }

    window.syncAllPendingPhotos = async function() {
        const fotos = await getSyncablePhotos();

        if (fotos.length === 0) {
            showToast('Nenhuma foto pendente para sincronizar.', 'info');
            return;
        }

        if (!confirm(`Enviar ${fotos.length} foto(s) pendente(s)?`)) return;

        let enviadas = 0;
        let erros = 0;

        for (const foto of fotos) {
            try {
                await uploadPendingPhoto(foto, { appBase: APP_BASE, csrfToken });
                await removePendingPhotoById(foto.id);
                enviadas++;
            } catch {
                erros++;
            }
        }

        await updateSyncBadge();
        showToast(`${enviadas} foto(s) enviada(s)` + (erros > 0 ? `, ${erros} erro(s)` : ''), erros > 0 ? 'warning' : 'success');
    };

    openFotosDB().then(() => cleanupOrphanedTempRecords()).catch(() => {});
    updateSyncBadge();
});
