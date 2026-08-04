/**
 * AgendaBarber Web - JavaScript Vanilla (ES6+)
 * Manipulações de interface, máscaras e requisições AJAX
 */

document.addEventListener('DOMContentLoaded', () => {
    aplicarMascaraTelefone();
    validarConfirmacaoDeSenha();
});

/**
 * Máscara progressiva de telefone brasileiro: (00) 0000-0000 e (00) 00000-0000.
 */
function aplicarMascaraTelefone() {
    const campos = document.querySelectorAll('[data-mascara="telefone"]');

    campos.forEach((campo) => {
        const formatar = () => {
            const digitos = campo.value.replace(/\D/g, '').slice(0, 11);

            if (digitos.length === 0) {
                campo.value = '';
                return;
            }

            let saida = '(' + digitos.slice(0, 2);

            if (digitos.length > 2) {
                const corte = digitos.length > 10 ? 7 : 6;
                saida += ') ' + digitos.slice(2, corte);

                if (digitos.length > corte) {
                    saida += '-' + digitos.slice(corte);
                }
            }

            campo.value = saida;
        };

        campo.addEventListener('input', formatar);
        formatar();
    });
}

/**
 * Aviso imediato quando a confirmação de senha diverge, antes do envio.
 * A validação definitiva continua sendo feita no servidor.
 */
function validarConfirmacaoDeSenha() {
    const confirmacao = document.querySelector('input[name="senha_confirmacao"]');
    if (!confirmacao) {
        return;
    }

    const formulario = confirmacao.closest('form');
    const senha = formulario ? formulario.querySelector('input[name="senha"]') : null;
    if (!senha) {
        return;
    }

    const conferir = () => {
        confirmacao.setCustomValidity(
            confirmacao.value !== '' && confirmacao.value !== senha.value
                ? 'As senhas não conferem.'
                : ''
        );
    };

    senha.addEventListener('input', conferir);
    confirmacao.addEventListener('input', conferir);
}
