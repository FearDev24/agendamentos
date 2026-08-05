/**
 * AgendaBarber Web - Checkout
 *
 * Cronômetro da reserva, cópia do código Pix, verificação automática da
 * confirmação do pagamento e tokenização do cartão.
 */

document.addEventListener('DOMContentLoaded', () => {
    iniciarCronometroCheckout();
    iniciarCopiaPix();
    iniciarVerificacaoDePagamento();
    iniciarFormularioCartao();
});

/* ------------------------------------------------------------------ */
/* Cronômetro regressivo da reserva                                    */
/* ------------------------------------------------------------------ */

function iniciarCronometroCheckout() {
    const elemento = document.getElementById('cronometro');
    if (!elemento) {
        return;
    }

    const expiraEm = new Date(elemento.dataset.expira.replace(' ', 'T')).getTime();

    const atualizar = () => {
        const restante = expiraEm - Date.now();

        if (restante <= 0) {
            elemento.textContent = '00:00';
            elemento.classList.add('cronometro-esgotado');
            clearInterval(intervalo);
            window.location.reload();
            return;
        }

        const minutos = Math.floor(restante / 60000);
        const segundos = Math.floor((restante % 60000) / 1000);

        elemento.textContent =
            String(minutos).padStart(2, '0') + ':' + String(segundos).padStart(2, '0');

        // Últimos 2 minutos ganham destaque de alerta
        elemento.classList.toggle('cronometro-alerta', restante < 120000);
    };

    atualizar();
    const intervalo = setInterval(atualizar, 1000);
}

/* ------------------------------------------------------------------ */
/* Copiar o código Pix                                                 */
/* ------------------------------------------------------------------ */

function iniciarCopiaPix() {
    const botao = document.getElementById('btn-copiar-pix');
    if (!botao) {
        return;
    }

    botao.addEventListener('click', async () => {
        const campo = document.getElementById(botao.dataset.alvo);
        const rotuloOriginal = botao.textContent;

        try {
            await navigator.clipboard.writeText(campo.value);
        } catch (erro) {
            // Contingência para navegadores sem a Clipboard API
            campo.select();
            document.execCommand('copy');
        }

        botao.textContent = 'Código copiado!';
        botao.disabled = true;

        setTimeout(() => {
            botao.textContent = rotuloOriginal;
            botao.disabled = false;
        }, 2500);
    });
}

/* ------------------------------------------------------------------ */
/* Verificação automática da confirmação (polling)                     */
/* ------------------------------------------------------------------ */

function iniciarVerificacaoDePagamento() {
    const contexto = window.AGENDABARBER_CHECKOUT;

    if (!contexto || !contexto.aguardando) {
        return;
    }

    // Consulta a cada 5 segundos: o pagamento por Pix é confirmado pelo
    // webhook, sem qualquer ação do cliente na tela.
    const intervalo = setInterval(async () => {
        try {
            const resposta = await fetch(contexto.urlStatus, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });

            if (!resposta.ok) {
                return;
            }

            const dados = await resposta.json();

            if (dados.confirmado) {
                clearInterval(intervalo);
                window.location.reload();
            }

        } catch (erro) {
            // Falha de rede momentânea: a próxima tentativa cobre
        }
    }, 5000);
}

/* ------------------------------------------------------------------ */
/* Cartão de crédito                                                   */
/* ------------------------------------------------------------------ */

/**
 * Em sandbox, gera um token fictício conforme o resultado escolhido.
 *
 * Em produção, os campos são controlados pelo SDK JS do Mercado Pago, que
 * tokeniza os dados no navegador. O número do cartão, o CVV e a validade
 * nunca são enviados a este servidor.
 */
function iniciarFormularioCartao() {
    const formulario = document.getElementById('form-cartao');
    const contexto = window.AGENDABARBER_CHECKOUT;

    if (!formulario || !contexto) {
        return;
    }

    formulario.addEventListener('submit', (evento) => {
        const campoToken = document.getElementById('token-cartao');

        if (campoToken.value !== '') {
            return; // token já obtido
        }

        evento.preventDefault();

        if (contexto.sandbox) {
            const resultado = document.getElementById('resultado-simulado').value;
            campoToken.value = 'sandbox-' + resultado + '-' + Date.now();
            document.getElementById('metodo-cartao').value = 'master';
            formulario.submit();
            return;
        }

        // Produção: a tokenização é feita pelo SDK do Mercado Pago.
        // A inicialização do SDK entra junto com as credenciais reais.
        console.warn('SDK do Mercado Pago não inicializado: configure as credenciais de produção.');
    });
}
