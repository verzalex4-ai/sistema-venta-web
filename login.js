// Manejo del login en modal - VERSION CON DEBUG
function handleLogin(event) {
    event.preventDefault();
    
    console.log('=== INICIANDO LOGIN ===');
    
    const usuario = document.getElementById('usuario').value.trim();
    const password = document.getElementById('password').value;
    const spinner = document.getElementById('loadingSpinner');
    const buttonText = document.getElementById('buttonText');
    const errorDiv = document.getElementById('loginError');
    const successDiv = document.getElementById('loginSuccess');
    const form = document.getElementById('loginForm');
    
    console.log('Usuario ingresado:', usuario);
    console.log('Password ingresado:', password ? '***' : '(vacío)');
    
    // Validar campos
    if (!usuario || !password) {
        console.error('Campos vacíos detectados');
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
    
    // Crear FormData
    const formData = new FormData();
    formData.append('usuario', usuario);
    formData.append('password', password);
    
    console.log('Enviando petición a login_ajax.php...');
    
    // Enviar petición
    fetch('login_ajax.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) {
        console.log('Respuesta recibida - Status:', response.status);
        console.log('Content-Type:', response.headers.get('content-type'));
        
        if (!response.ok) {
            throw new Error('Error HTTP ' + response.status);
        }
        
        return response.text();
    })
    .then(function(text) {
        console.log('=== RESPUESTA DEL SERVIDOR (RAW) ===');
        console.log(text);
        console.log('=== FIN RESPUESTA RAW ===');
        
        // Intentar parsear JSON
        let data;
        try {
            data = JSON.parse(text);
            console.log('JSON parseado correctamente:', data);
        } catch(e) {
            console.error('ERROR al parsear JSON:', e);
            console.error('Texto recibido:', text);
            throw new Error('El servidor no devolvió JSON válido');
        }
        
        if (data.success) {
            console.log('✓ Login EXITOSO');
            console.log('Rol:', data.role);
            console.log('Nombre:', data.nombre);
            
            // Login exitoso
            spinner.classList.remove('show');
            buttonText.innerHTML = '<i class="fas fa-check-circle me-2"></i>¡Éxito!';
            successDiv.classList.add('show');
            
            console.log('Redirigiendo a dashboard.php en 1 segundo...');
            
            setTimeout(function() {
                console.log('Ejecutando redirección...');
                window.location.replace('dashboard.php');
            }, 1000);
            
        } else {
            console.error('✗ Login FALLIDO:', data.message);
            
            // Error en login
            spinner.classList.remove('show');
            buttonText.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión';
            form.style.opacity = '1';
            form.style.pointerEvents = 'auto';
            errorDiv.classList.add('show');
            document.getElementById('errorMessage').textContent = data.message || 'Usuario o contraseña incorrectos';
            document.getElementById('password').value = '';
            
            // Animación shake
            const modalContent = document.querySelector('.login-modal .modal-content');
            if (modalContent) {
                modalContent.style.animation = 'shake 0.5s';
                setTimeout(function() {
                    modalContent.style.animation = '';
                }, 500);
            }
        }
    })
    .catch(function(error) {
        console.error('=== ERROR EN EL PROCESO ===');
        console.error('Tipo:', error.name);
        console.error('Mensaje:', error.message);
        
        // Mostrar error
        spinner.classList.remove('show');
        buttonText.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión';
        form.style.opacity = '1';
        form.style.pointerEvents = 'auto';
        errorDiv.classList.add('show');
        document.getElementById('errorMessage').textContent = 'Error: ' + error.message;
    });
}

// Toggle password
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    if (passwordInput && toggleIcon) {
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
}

// Animación
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
        20%, 40%, 60%, 80% { transform: translateX(10px); }
    }
`;
document.head.appendChild(style);

// Events
document.addEventListener('DOMContentLoaded', function() {
    console.log('Login.js inicializado');
    const usuarioInput = document.getElementById('usuario');
    const passwordInput = document.getElementById('password');
    const errorDiv = document.getElementById('loginError');
    
    if (usuarioInput) {
        usuarioInput.addEventListener('input', function() {
            if (errorDiv) errorDiv.classList.remove('show');
        });
    }
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            if (errorDiv) errorDiv.classList.remove('show');
        });
    }
    
    const loginModal = document.getElementById('loginModal');
    if (loginModal) {
        loginModal.addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('loginForm');
            if (form) form.reset();
            if (errorDiv) errorDiv.classList.remove('show');
        });
    }
});