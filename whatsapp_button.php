<?php
// Get customized message if not set
if (!isset($default_message)) {
    $current_page = basename($_SERVER['PHP_SELF']);
    $default_message = "Hola, estoy interesado en sus productos.";

    if ($current_page === 'catalog.php') {
        $default_message = "Hola, estoy interesado en los productos de su catálogo.";
    } elseif ($current_page === 'cart.php') {
        $default_message = "Hola, tengo preguntas sobre mi pedido.";
    } elseif ($current_page === 'product.php' && isset($_GET['id'])) {
        $product_id = $_GET['id'];
        $default_message = "Hola, estoy interesado en el producto #$product_id.";
    }
}

// Set hardcoded admin mobile number
$mobile = "04143332662";

// Format mobile number for WhatsApp API
// Make sure it's international format (add 58 for Venezuela if needed)
if (substr($mobile, 0, 2) == "04") {
    $mobile = "58" . $mobile;
}

// Make sure it's just digits
$mobile = preg_replace('/[^0-9]/', '', $mobile);
?>

<!-- WhatsApp Floating Button -->
<div id="whatsapp-button-container" class="whatsapp-button">
    <a href="https://wa.me/<?php echo $mobile; ?>?text=<?php echo urlencode($default_message); ?>" 
       target="_blank" rel="noopener noreferrer" class="whatsapp-btn">
        <i class="fab fa-whatsapp"></i>
        <span class="whatsapp-text"></span>
    </a>
</div>

<!-- WhatsApp Button Styles -->
<style>
#whatsapp-button-container.whatsapp-button {
    position: fixed !important;
    bottom: 30px !important;
    right: 30px !important;
    z-index: 99999 !important; /* Ultra high z-index */
    filter: drop-shadow(0 0 10px rgba(37, 211, 102, 0.4));
    transform-style: preserve-3d;
    visibility: visible !important;
    opacity: 1 !important;
    display: flex !important;
    pointer-events: auto !important;
    transform: translateZ(0) !important; /* Force hardware acceleration */
    backface-visibility: visible !important;
    min-width: 60px !important;
    min-height: 60px !important;
}

#whatsapp-button-container .whatsapp-btn {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: linear-gradient(135deg, #4e6c41, #759c63) !important;
    color: white !important;
    padding: 12px 20px !important;
    border-radius: 50px !important;
    text-decoration: none !important;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3), 0 0 0 0 rgb(78,108,65,0.5) !important;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
    transform-style: preserve-3d !important;
    animation: whatsappPulse 2s infinite !important;
    will-change: transform, box-shadow !important; /* Performance optimization */
    white-space: nowrap !important;
}

#whatsapp-button-container .whatsapp-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at center, rgb(78,108,65,0.5) 0%, transparent 70%);
    border-radius: 50px;
    z-index: -1;
    opacity: 0.7;
    animation: whatsappGlow 3s ease-in-out infinite;
}

#whatsapp-button-container .whatsapp-btn:hover {
    background: linear-gradient(135deg, #759c63, #4e6c41) !important;
    color: white !important;
    transform: translateY(-5px) scale(1.05) !important;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4) !important;
    animation: none !important;
}

#whatsapp-button-container .whatsapp-btn i {
    font-size: 24px !important;
    margin-right: 10px !important;
    animation: shake 4s ease-in-out infinite !important;
}

#whatsapp-button-container .whatsapp-text {
    font-weight: 600 !important;
    letter-spacing: 0.5px !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
}

@keyframes whatsappPulse {
    0% {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3), 0 0 0 0 rgb(78,108,65,0.7);
    }
    50% {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3), 0 0 0 15px rgba(10, 77, 130, 0);
    }
    100% {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3), 0 0 0 0 rgba(10, 77, 130, 0);
    }
}

@keyframes whatsappGlow {
    0%, 100% { opacity: 0.7; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(1.2); }
}

@keyframes shake {
    0%, 100% { transform: rotate(0); }
    88%, 90%, 92%, 94%, 96%, 98% {
        transform: rotate(-10deg);
    }
    89%, 91%, 93%, 95%, 97%, 99% {
        transform: rotate(10deg);
    }
}

/* Responsive design with improved visibility */
@media (max-width: 768px) {
    #whatsapp-button-container.whatsapp-button {
        bottom: 20px !important;
        right: 20px !important;
    }
    
    #whatsapp-button-container .whatsapp-btn {
        padding: 12px !important;
        width: 60px !important;
        height: 60px !important;
        border-radius: 50% !important;
        justify-content: center !important;
    }
    
    #whatsapp-button-container .whatsapp-text {
        display: none !important; /* Hide text on mobile */
    }
    
    #whatsapp-button-container .whatsapp-btn i {
        font-size: 32px !important;
        margin-right: 0 !important;
    }
}
</style>

<!-- Improved JavaScript to ensure the button is always visible -->
<script>
(function() {
    // Run immediately and after DOM is loaded
    function fixWhatsAppButton() {
        // Remove any duplicate buttons first
        var buttons = document.querySelectorAll('.whatsapp-button');
        if (buttons.length > 1) {
            for (var i = 1; i < buttons.length; i++) {
                buttons[i].parentNode.removeChild(buttons[i]);
            }
        }
        
        // Get the WhatsApp button
        var button = document.getElementById('whatsapp-button-container');
        if (!button) return;
        
        // Ensure it has the correct CSS properties
        button.style.position = 'fixed';
        button.style.bottom = '30px';
        button.style.right = '30px';
        button.style.zIndex = '99999';
        button.style.display = 'flex';
        button.style.visibility = 'visible';
        button.style.opacity = '1';
    }
    
    // Run immediately
    fixWhatsAppButton();
    
    // Run after DOM loaded
    document.addEventListener('DOMContentLoaded', fixWhatsAppButton);
    
    // Run on scroll
    window.addEventListener('scroll', fixWhatsAppButton);
    
    // Run periodically to make sure it stays visible
    setInterval(fixWhatsAppButton, 1000);
    
    // Also run after a small delay to catch any late page modifications
    setTimeout(fixWhatsAppButton, 500);
    setTimeout(fixWhatsAppButton, 2000);
})();
</script> 