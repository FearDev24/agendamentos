/**
 * AgendaBarber Web - Instalação como aplicativo (PWA)
 *
 * Registra o Service Worker e oferece a instalação na tela inicial.
 *
 * O Chrome/Edge no Android emite o evento `beforeinstallprompt`, que permite
 * abrir o instalador nativo. O Safari no iPhone não oferece esse evento: lá a
 * instalação é manual, então mostramos as instruções do "Compartilhar →
 * Adicionar à Tela de Início".
 */

document.addEventListener('DOMContentLoaded', () => {
    registrarServiceWorker();
    prepararConviteDeInstalacao();
});

/* ------------------------------------------------------------------ */
/* Service Worker                                                      */
/* ------------------------------------------------------------------ */

function registrarServiceWorker() {
    if (!('serviceWorker' in navigator) || !window.AGENDABARBER_PWA) {
        return;
    }

    navigator.serviceWorker
        .register(window.AGENDABARBER_PWA.urlServiceWorker, {
            scope: window.AGENDABARBER_PWA.escopo
        })
        .catch(() => {
            // Sem Service Worker o site segue funcionando normalmente,
            // apenas sem instalação e sem cache offline.
        });
}

/* ------------------------------------------------------------------ */
/* Convite de instalação                                               */
/* ------------------------------------------------------------------ */

const CHAVE_DISPENSA = 'agendabarber_convite_instalacao_dispensado';

/**
 * Exibe o convite com a transição de entrada.
 *
 * A leitura de `offsetHeight` força o navegador a recalcular o layout,
 * registrando o estado inicial (fora da tela) antes de a classe entrar — é o
 * que faz a transição acontecer. Usamos isso em vez de `requestAnimationFrame`
 * porque o rAF não dispara em abas em segundo plano, e o banner ficaria
 * permanentemente invisível.
 */
function mostrarConvite(banner) {
    banner.hidden = false;
    void banner.offsetHeight;
    banner.classList.add('convite-visivel');
}

/** Recolhe o convite, aguardando a transição terminar antes de ocultar. */
function esconderConvite(banner) {
    banner.classList.remove('convite-visivel');
    setTimeout(() => { banner.hidden = true; }, 300);
}
/** Após dispensar, só voltamos a convidar depois deste período */
const DIAS_ATE_CONVIDAR_DE_NOVO = 14;

let eventoInstalacao = null;

function prepararConviteDeInstalacao() {
    const banner = document.getElementById('convite-instalacao');

    if (!banner || jaEstaInstalado() || conviteFoiDispensadoRecentemente()) {
        return;
    }

    const botaoInstalar = document.getElementById('btn-instalar-app');
    const botaoDispensar = document.getElementById('btn-dispensar-instalacao');
    const instrucoesIos = document.getElementById('instrucoes-ios');

    botaoDispensar.addEventListener('click', () => {
        esconderConvite(banner);
        registrarDispensa();
    });

    // iPhone e iPad: não há instalador automático, apenas instruções
    if (ehIos()) {
        botaoInstalar.hidden = true;
        document.getElementById('instrucoes-padrao').hidden = true;
        instrucoesIos.hidden = false;
        mostrarConvite(banner);
        return;
    }

    // Android/desktop: o navegador avisa quando a instalação é possível
    window.addEventListener('beforeinstallprompt', (evento) => {
        evento.preventDefault();
        eventoInstalacao = evento;
        mostrarConvite(banner);
    });

    botaoInstalar.addEventListener('click', async () => {
        if (!eventoInstalacao) {
            return;
        }

        eventoInstalacao.prompt();
        const escolha = await eventoInstalacao.userChoice;

        eventoInstalacao = null;
        esconderConvite(banner);

        if (escolha.outcome === 'dismissed') {
            registrarDispensa();
        }
    });

    window.addEventListener('appinstalled', () => {
        esconderConvite(banner);
        localStorage.removeItem(CHAVE_DISPENSA);
    });
}

/**
 * Detecta se a página já está rodando como aplicativo instalado.
 */
function jaEstaInstalado() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function ehIos() {
    const ua = window.navigator.userAgent;
    // iPadOS recente se identifica como Mac; o toque na tela o diferencia
    return /iPad|iPhone|iPod/.test(ua)
        || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
}

function conviteFoiDispensadoRecentemente() {
    try {
        const quando = parseInt(localStorage.getItem(CHAVE_DISPENSA) || '0', 10);
        if (!quando) {
            return false;
        }

        const dias = (Date.now() - quando) / 86400000;
        return dias < DIAS_ATE_CONVIDAR_DE_NOVO;

    } catch (erro) {
        return false; // navegação privada pode bloquear o localStorage
    }
}

function registrarDispensa() {
    try {
        localStorage.setItem(CHAVE_DISPENSA, String(Date.now()));
    } catch (erro) {
        // Sem localStorage o convite reaparece: comportamento aceitável
    }
}
