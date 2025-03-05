
document.addEventListener('DOMContentLoaded', function () {
    // Selecciona el formulario
    const form = document.querySelector('form');

    // Añade el evento 'submit' al formulario
    form.addEventListener('submit', function (e) {
        // Evita que el formulario se envíe hasta validar los campos
        e.preventDefault();

        // Selecciona los campos de email y contraseña
        const emailField = document.getElementById('lemail');
        const passwordField = document.getElementById('lpassword');

        // Variables para almacenar los mensajes de error
        let emailError = '';
        let passwordError = '';

        // Valida el email
        if (!validateEmail(emailField.value)) {
            emailError = 'Por favor, ingrese un correo electrónico válido.';
        }

        // Valida la contraseña
        if (passwordField.value.trim() === '') {
            passwordError = 'Por favor, ingrese su contraseña.';
        }

        // Mostrar los errores en pantalla si los hay
        if (emailError || passwordError) {
            alert(`${emailError} ${passwordError}`);
        } else {
            // Si no hay errores, envía el formulario
            form.submit();
        }
    });

    // Función para validar formato de email
    function validateEmail(email) {
        const re = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        return re.test(email);
    }
});

