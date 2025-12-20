// Manejo del login en modal
function handleLogin(event) {
    event.preventDefault();
    
    const usuario = document.getElementById('usuario').value.trim();
    const password = document.getElementById('password').value;
    const spinner = document.getElementById('loadingSpinner');
    const buttonText = document.getElementById('buttonText');
    const errorDiv = document.getElementById('loginError');
    const successDiv = document.getElementById('loginSuccess');
    const form = document.getElementById('loginForm');
    
    // Validar campos
    if (!usuario || !password) {
        errorDiv.classList.add('show');
        document.getElementById('errorMessage').textContent = 'Por favor completa todos los campos';
        return;
    }
    
    // Mostrar spinner
    spinner.classList.add('show');
    buttonText.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verificando...';
    form.style.opacity = '0.6';
    form.style.pointerEvents = 'none';
    errorDiv.classList.remove('show');
    successDiv.classList.remove('show');
    
    // Crear FormData para enviar
    const formData = new FormData();
    formData.append('usuario', usuario);
    formData.append('password', password);
    
    // Enviar datos vía AJAX a login_ajax.php
    fetch('login_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.text();
    })
    .then(function(text) {
        console.log('Respuesta del servidor:', text);
        try {
            return JSON.parse(text);
        } catch(e) {
            console.error('Respuesta no es JSON válido:', text);
            throw new Error('Respuesta inválida del servidor');
        }
    })
    .then(function(data) {
        if (data.success) {
            // Login exitoso
            spinner.classList.remove('show');
            buttonText.innerHTML = '<i class="fas fa-check-circle me-2"></i>¡Éxito!';
            successDiv.classList.add('show');
            
            setTimeout(function() {
                window.location.href = 'dashboard.php';
            }, 800);
        } else {
            // Error en login
            spinner.classList.remove('show');
            buttonText.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión';
            form.style.opacity = '1';
            form.style.pointerEvents = 'auto';
            errorDiv.classList.add('show');
            document.getElementById('errorMessage').textContent = data.message || 'Usuario o contraseña incorrectos';
            document.getElementById('password').value = '';
            
            // Shake animation
            const modalContent = document.querySelector('.login-modal .modal-content');
            modalContent.style.animation = 'shake 0.5s';
            setTimeout(function() {
                modalContent.style.animation = '';
            }, 500);
        }
    })
    .catch(function(error) {
        console.error('Error completo:', error);
        spinner.classList.remove('show');
        buttonText.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión';
        form.style.opacity = '1';
        form.style.pointerEvents = 'auto';
        errorDiv.classList.add('show');
        document.getElementById('errorMessage').textContent = 'Error de conexión: ' + error.message;
    });
}

// Toggle password visibility
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Animación shake para errores
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
        20%, 40%, 60%, 80% { transform: translateX(10px); }
    }
`;
document.head.appendChild(style);

// Limpiar errores al escribir
document.addEventListener('DOMContentLoaded', function() {
    const usuarioInput = document.getElementById('usuario');
    const passwordInput = document.getElementById('password');
    const errorDiv = document.getElementById('loginError');
    
    if (usuarioInput) {
        usuarioInput.addEventListener('input', function() {
            errorDiv.classList.remove('show');
        });
    }
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            errorDiv.classList.remove('show');
        });
    }

    // Limpiar formulario al cerrar modal
    const loginModal = document.getElementById('loginModal');
    if (loginModal) {
        loginModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('loginForm').reset();
            errorDiv.classList.remove('show');
            document.getElementById('loginSuccess').classList.remove('show');
            const buttonText = document.getElementById('buttonText');
            buttonText.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión';
            document.getElementById('loginForm').style.opacity = '1';
            document.getElementById('loginForm').style.pointerEvents = 'auto';
        });
    }
});