// Main JavaScript for LocNetServe documentation
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Terminal animation
    animateTerminal();
    
    // Video placeholder interactions
    initVideoPlaceholders();
    
    // Mobile menu toggle (if needed)
    initMobileMenu();
});

function animateTerminal() {
    const terminal = document.querySelector('.terminal-content');
    if (!terminal) return;
    
    const lines = terminal.innerHTML.split('<br>');
    terminal.innerHTML = '';
    
    let index = 0;
    function typeLine() {
        if (index < lines.length) {
            terminal.innerHTML += lines[index] + '<br>';
            terminal.scrollTop = terminal.scrollHeight;
            index++;
            setTimeout(typeLine, 200);
        }
    }
    
    setTimeout(typeLine, 1000);
}

function initVideoPlaceholders() {
    document.querySelectorAll('.video-placeholder').forEach(placeholder => {
        placeholder.addEventListener('click', function(e) {
            if (this.querySelector('a')) {
                // You can add lightbox functionality here later
                console.log('Video clicked - would open video player');
            }
        });
    });
}

function initMobileMenu() {
    // Add mobile menu functionality if needed
    console.log('Mobile menu initialized');
}

// Search functionality for documentation
function initSearch() {
    // This can be expanded for documentation search
    console.log('Search functionality placeholder');
}
if (window.location.pathname.includes('404') || document.title.includes('404')) {
    window.location.href = '/404.html';
}