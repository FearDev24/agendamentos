/**
 * AgendaBarber Web - Service Worker
 *
 * Fica na raiz do projeto de propósito: o escopo de um Service Worker é o
 * diretório onde ele reside, então daqui ele controla toda a aplicação.
 *
 * Responsabilidades:
 *  1. Habilitar a instalação como aplicativo (requisito dos navegadores);
 *  2. Guardar os arquivos estáticos em cache, deixando a navegação mais rápida;
 *  3. Exibir uma página de aviso quando o celular estiver sem internet.
 *
 * Deliberadamente NÃO faz cache de páginas HTML nem de respostas de API:
 * horários livres, agendamentos e pagamentos precisam vir sempre do servidor,
 * sob risco de o cliente ver uma agenda desatualizada.
 */

const VERSAO = 'agendabarber-v1';
const BASE = new URL(self.registration.scope).pathname;

/** Arquivos que valem a pena guardar já na instalação */
const ARQUIVOS_ESSENCIAIS = [
    BASE + 'public/assets/css/style.css',
    BASE + 'public/assets/vendor/bootstrap/css/bootstrap.min.css',
    BASE + 'public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js',
    BASE + 'public/assets/js/main.js',
    BASE + 'public/assets/img/logojablonskiprincipal.png',
    BASE + 'public/assets/img/pwa/icone-192.png',
];

self.addEventListener('install', (evento) => {
    evento.waitUntil(
        caches.open(VERSAO)
            // addAll falha inteiro se um arquivo falhar; adicionamos um a um
            // para que uma ausência isolada não impeça a instalação.
            .then((cache) => Promise.allSettled(
                ARQUIVOS_ESSENCIAIS.map((url) => cache.add(url))
            ))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches.keys()
            .then((chaves) => Promise.all(
                chaves.filter((chave) => chave !== VERSAO)
                      .map((chave) => caches.delete(chave))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (evento) => {
    const requisicao = evento.request;

    // Só interceptamos leituras simples da própria origem
    if (requisicao.method !== 'GET' || new URL(requisicao.url).origin !== self.location.origin) {
        return;
    }

    const caminho = new URL(requisicao.url).pathname;
    const ehEstatico = /\.(css|js|png|jpg|jpeg|webp|svg|woff2?)$/i.test(caminho);

    if (ehEstatico) {
        // Estáticos: responde do cache e atualiza em segundo plano
        evento.respondWith(
            caches.match(requisicao).then((emCache) => {
                const daRede = fetch(requisicao).then((resposta) => {
                    if (resposta && resposta.status === 200) {
                        const copia = resposta.clone();
                        caches.open(VERSAO).then((cache) => cache.put(requisicao, copia));
                    }
                    return resposta;
                }).catch(() => emCache);

                return emCache || daRede;
            })
        );
        return;
    }

    // Páginas e APIs: sempre da rede. Sem internet, avisa em vez de mostrar
    // informação desatualizada.
    evento.respondWith(
        fetch(requisicao).catch(() => {
            if (requisicao.mode === 'navigate') {
                return new Response(paginaSemConexao(), {
                    status: 503,
                    headers: { 'Content-Type': 'text/html; charset=utf-8' }
                });
            }

            return new Response(
                JSON.stringify({ erro: 'Sem conexão com a internet.' }),
                { status: 503, headers: { 'Content-Type': 'application/json' } }
            );
        })
    );
});

function paginaSemConexao() {
    return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sem conexão</title>
    <style>
        body {
            margin: 0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background-color: #0f1416; color: #dfe3e6;
            font-family: system-ui, -apple-system, sans-serif;
            text-align: center; padding: 24px;
        }
        .caixa {
            max-width: 380px; padding: 32px;
            background-color: #1b2022; border: 1px solid #4e4639; border-radius: 4px;
        }
        h1 { color: #e9c176; font-size: 20px; margin: 0 0 12px; }
        p { color: #d1c5b4; font-size: 14px; line-height: 20px; margin: 0 0 24px; }
        button {
            background-color: #c5a059; color: #412d00; border: none;
            padding: 12px 28px; border-radius: 4px; font-weight: 600; font-size: 14px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="caixa">
        <h1>Sem conexão</h1>
        <p>Não foi possível carregar esta página. Verifique sua internet e tente novamente.</p>
        <button onclick="location.reload()">Tentar novamente</button>
    </div>
</body>
</html>`;
}
