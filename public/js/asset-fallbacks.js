// Enhanced Asset Fallback System with Placeholder Support
document.addEventListener('DOMContentLoaded', function() {
    
    // Placeholder URLs
    const PLACEHOLDERS = {
        user: '/images/placeholders/user-default.svg',
        category: '/images/placeholders/category-default.svg',
        section: '/images/placeholders/section-default.svg'
    };
    
    // Handle image loading errors
    function handleImageError(img) {
        const src = img.getAttribute('src');
        
        // Prevent infinite error loops
        if (img.dataset.fallbackApplied) {
            return;
        }
        img.dataset.fallbackApplied = 'true';
        
        // Determine placeholder type based on image source or class
        let placeholderType = 'user';
        
        if (src && (src.includes('avatars') || img.classList.contains('avatar') || img.closest('.avatar'))) {
            placeholderType = 'user';
        } else if (src && (src.includes('categories') || img.classList.contains('category-image'))) {
            placeholderType = 'category';
        } else if (src && (src.includes('sections') || img.classList.contains('section-image'))) {
            placeholderType = 'section';
        }
        
        // Try to use initials for user avatars if name is available
        if (placeholderType === 'user') {
            const userName = img.getAttribute('alt') || img.dataset.name;
            if (userName && userName !== 'User' && userName.trim()) {
                img.src = generateInitialsAvatar(userName, img.width || 40);
                return;
            }
        }
        
        // Use appropriate placeholder
        img.src = PLACEHOLDERS[placeholderType];
        img.onerror = null; // Remove error handler to prevent loops
    }
    
    // Generate initials-based avatar
    function generateInitialsAvatar(name, size = 40) {
        // Generate consistent color from name
        let hash = 0;
        for (let i = 0; i < name.length; i++) {
            hash = name.charCodeAt(i) + ((hash << 5) - hash);
        }
        
        const colors = [
            '#007bff', '#6f42c1', '#e83e8c', '#dc3545', '#fd7e14',
            '#ffc107', '#28a745', '#20c997', '#17a2b8', '#6c757d'
        ];
        const bgColor = colors[Math.abs(hash) % colors.length];
        
        // Extract initials
        const words = name.trim().split(' ');
        let initials = '';
        for (const word of words) {
            if (word.length > 0) {
                initials += word[0].toUpperCase();
                if (initials.length >= 2) break;
            }
        }
        if (!initials) initials = 'U';
        
        // Create SVG
        const svg = `
            <svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" xmlns="http://www.w3.org/2000/svg">
                <circle cx="${size/2}" cy="${size/2}" r="${size/2}" fill="${bgColor}"/>
                <text x="50%" y="50%" text-anchor="middle" dy="0.35em" 
                      font-family="Arial, sans-serif" font-size="${size * 0.4}" 
                      fill="white" font-weight="600">${initials}</text>
            </svg>
        `;
        
        return 'data:image/svg+xml;base64,' + btoa(svg);
    }
    
    // Apply error handlers to existing images
    function setupImageFallbacks() {
        const images = document.querySelectorAll('img');
        images.forEach(img => {
            // Skip if already has fallback
            if (img.dataset.fallbackSetup) return;
            img.dataset.fallbackSetup = 'true';
            
            img.addEventListener('error', function() {
                handleImageError(this);
            });
            
            // Check if image is already broken
            if (img.complete && img.naturalHeight === 0 && img.src) {
                handleImageError(img);
            }
        });
    }
    
    // Initial setup
    setupImageFallbacks();
    
    // Handle dynamically added images
    const observer = new MutationObserver(function(mutations) {
        let hasNewImages = false;
        
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) { // Element node
                    if (node.tagName === 'IMG' || node.querySelectorAll) {
                        hasNewImages = true;
                    }
                }
            });
        });
        
        if (hasNewImages) {
            setupImageFallbacks();
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Handle missing favicon
    const favicon = document.querySelector('link[rel="icon"]');
    if (favicon && !favicon.href) {
        // Create a simple favicon
        const canvas = document.createElement('canvas');
        canvas.width = 32;
        canvas.height = 32;
        const ctx = canvas.getContext('2d');
        
        ctx.fillStyle = '#696cff';
        ctx.fillRect(0, 0, 32, 32);
        
        ctx.fillStyle = 'white';
        ctx.font = 'bold 20px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('V', 16, 22);
        
        favicon.href = canvas.toDataURL();
    }
    
    // Check for missing CSS files and add fallback styles
    const links = document.querySelectorAll('link[rel="stylesheet"]');
    links.forEach(function(link) {
        link.addEventListener('error', function() {
            console.warn('Failed to load CSS:', this.href);
            if (this.href.includes('perfect-scrollbar')) {
                addFallbackScrollbarStyles();
            }
        });
    });
    
    // Global helper functions
    window.ImageFallbacks = {
        generateInitialsAvatar: generateInitialsAvatar,
        
        setUserAvatar: function(imgElement, userName, size = 40) {
            if (userName && userName.trim()) {
                imgElement.src = generateInitialsAvatar(userName, size);
            } else {
                imgElement.src = PLACEHOLDERS.user;
            }
        },
        
        setCategoryImage: function(imgElement) {
            imgElement.src = PLACEHOLDERS.category;
        },
        
        setSectionImage: function(imgElement) {
            imgElement.src = PLACEHOLDERS.section;
        }
    };
    
    // Legacy support
    window.AssetFallbacks = window.ImageFallbacks;
    window.createInitialsAvatar = generateInitialsAvatar;
});

function addFallbackScrollbarStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .ps {
            overflow: hidden !important;
            overflow-anchor: none;
            -ms-overflow-style: none;
            touch-action: auto;
            -ms-touch-action: auto;
        }
        
        .ps__rail-x, .ps__rail-y {
            display: none !important;
        }
        
        .ps--active-x > .ps__rail-x,
        .ps--active-y > .ps__rail-y {
            display: block !important;
            background-color: transparent;
        }
    `;
    document.head.appendChild(style);
}