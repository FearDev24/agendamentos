/**
 * AgendaBarber Web - Wizard de agendamento
 *
 * Passo 1: soma dinâmica de valor e duração dos serviços;
 * Passo 2: grade de horários carregada via Fetch API;
 * Checkout: cronômetro regressivo da reserva.
 */

document.addEventListener('DOMContentLoaded', () => {
    iniciarSomaDeServicos();
    iniciarGradeDeHorarios();
    iniciarCronometro();
});

/* ------------------------------------------------------------------ */
/* Passo 1 - Seleção de serviços                                       */
/* ------------------------------------------------------------------ */

function iniciarSomaDeServicos() {
    const formulario = document.getElementById('form-servicos');
    if (!formulario) {
        return;
    }

    const caixas = formulario.querySelectorAll('input[name="servicos[]"]');
    const campoValor = document.getElementById('total-valor');
    const campoDuracao = document.getElementById('total-duracao');
    const botao = document.getElementById('btn-continuar');

    const recalcular = () => {
        let valor = 0;
        let duracao = 0;
        let marcados = 0;

        caixas.forEach((caixa) => {
            if (caixa.checked) {
                valor += parseFloat(caixa.dataset.preco || '0');
                duracao += parseInt(caixa.dataset.duracao || '0', 10);
                marcados += 1;
            }
            caixa.closest('.cartao-servico').classList.toggle('cartao-servico-ativo', caixa.checked);
        });

        campoValor.textContent = formatarMoeda(valor);
        campoDuracao.textContent = formatarDuracao(duracao);
        botao.disabled = marcados === 0;
    };

    caixas.forEach((caixa) => caixa.addEventListener('change', recalcular));
    recalcular();
}

function formatarMoeda(valor) {
    return 'R$ ' + valor.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function formatarDuracao(minutos) {
    if (minutos < 60) {
        return minutos + ' min';
    }

    const horas = Math.floor(minutos / 60);
    const resto = minutos % 60;

    return resto === 0 ? horas + 'h' : horas + 'h ' + resto + 'min';
}

/* ------------------------------------------------------------------ */
/* Passo 2 - Grade de horários via AJAX                                */
/* ------------------------------------------------------------------ */

function iniciarGradeDeHorarios() {
    const grade = document.getElementById('grade-horarios');
    if (!grade || !window.AGENDABARBER) {
        return;
    }

    const campoData = document.getElementById('data');
    const campoProfissional = document.getElementById('profissional');
    const formulario = document.getElementById('form-horario');
    const horaEscolhida = document.getElementById('hora-escolhida');
    const profissionalEscolhido = document.getElementById('profissional-escolhido');

    const carregar = async () => {
        const data = campoData.value;

        if (!data) {
            grade.innerHTML = '<p class="text-muted-custom mb-0">Selecione uma data para ver os horários.</p>';
            return;
        }

        grade.innerHTML = '<p class="text-muted-custom mb-0">Buscando horários disponíveis...</p>';

        const parametros = new URLSearchParams({
            data: data,
            profissional: campoProfissional.value
        });

        try {
            const resposta = await fetch(`${window.AGENDABARBER.urlHorarios}?${parametros}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });

            if (!resposta.ok) {
                throw new Error('HTTP ' + resposta.status);
            }

            const dados = await resposta.json();
            renderizarHorarios(dados);

        } catch (erro) {
            grade.innerHTML = '<p class="text-erro mb-0">Não foi possível carregar os horários. Tente novamente.</p>';
        }
    };

    const renderizarHorarios = (dados) => {
        if (!dados.horarios || dados.horarios.length === 0) {
            grade.innerHTML = `<p class="text-muted-custom mb-0">${escaparHtml(dados.aviso || 'Nenhum horário disponível.')}</p>`;
            return;
        }

        const botoes = dados.horarios.map((slot) => `
            <button type="button" class="botao-horario"
                    data-hora="${escaparHtml(slot.hora)}"
                    data-profissional="${escaparHtml(String(slot.profissional_id))}"
                    title="Com ${escaparHtml(slot.profissional_nome)} · termina às ${escaparHtml(slot.hora_fim)}">
                ${escaparHtml(slot.hora)}
            </button>
        `).join('');

        grade.innerHTML = `<div class="lista-horarios">${botoes}</div>`;

        grade.querySelectorAll('.botao-horario').forEach((botao) => {
            botao.addEventListener('click', () => {
                horaEscolhida.value = botao.dataset.hora;
                profissionalEscolhido.value = botao.dataset.profissional;
                formulario.submit();
            });
        });
    };

    campoData.addEventListener('change', carregar);
    campoProfissional.addEventListener('change', carregar);
    carregar();
}

/**
 * Escapa texto vindo do servidor antes de inserir no DOM.
 */
function escaparHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}

/* ------------------------------------------------------------------ */
/* Checkout - Cronômetro regressivo da reserva                         */
/* ------------------------------------------------------------------ */

function iniciarCronometro() {
    const elemento = document.getElementById('cronometro');
    if (!elemento) {
        return;
    }

    // O horário vem como 'YYYY-MM-DD HH:MM:SS' no fuso do servidor
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

        elemento.classList.toggle('cronometro-alerta', restante < 120000);
    };

    atualizar();
    const intervalo = setInterval(atualizar, 1000);
}
