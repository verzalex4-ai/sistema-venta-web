<?php
/**
 * HEADER GLOBAL OPTIMIZADO
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = $page_title ?? 'Sistema de Ventas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <?php if (isset($extra_css) && is_array($extra_css)): ?>
        <?php foreach ($extra_css as $css): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (isset($chart_js) && $chart_js): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php endif; ?>

<style>
        body {
            background-color: var(--bg-secondary);
        }
        
        #wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Desktop: sidebar fijo y visible */
        #sidebar-wrapper {
            width: 250px;
            background: var(--bg-dark);
            color: white;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        
        #content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Mobile: sidebar completamente oculto */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                position: fixed;
                top: 0;
                left: -250px; /* Oculto fuera de pantalla */
                width: 250px;
                height: 100vh;
                z-index: 1000;
                overflow-y: auto;
                visibility: hidden; /* Invisible e inaccesible */
                transition: left 0.3s ease, visibility 0s 0.3s; /* Delay en visibility */
            }
            
            #sidebar-wrapper.show {
                left: 0;
                visibility: visible;
                transition: left 0.3s ease, visibility 0s; /* Sin delay al mostrar */
                box-shadow: 2px 0 15px rgba(0, 0, 0, 0.3);
            }
            
            /* Overlay oscuro cuando sidebar está abierto */
            #sidebar-wrapper.show::after {
                content: '';
                position: fixed;
                top: 0;
                left: 250px;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: -1;
            }
            
            #content-wrapper {
                width: 100%;
                margin-left: 0;
            }
        }
    </style>

</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="btn btn-primary d-md-none position-fixed" id="sidebarToggle" style="top: 10px; left: 10px; z-index: 1001;">
        <i class="fas fa-bars"></i>
    </button>
    
    <div id="wrapper">