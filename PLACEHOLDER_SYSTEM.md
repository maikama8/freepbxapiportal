# Placeholder Image System

This document describes the comprehensive placeholder image system implemented for the FreePBX VoIP Platform.

## Overview

The placeholder system provides automatic fallbacks for missing images with three types of placeholders:
- **User placeholders**: For user avatars with initials generation
- **Category placeholders**: For category/section images
- **Section placeholders**: For general content images

## Files Created

### SVG Placeholders
- `public/images/placeholders/user-default.svg` - Default user avatar
- `public/images/placeholders/category-default.svg` - Category image placeholder
- `public/images/placeholders/section-default.svg` - Section image placeholder

### PHP Classes
- `app/Helpers/ImageHelper.php` - Core image handling logic
- `app/Helpers/PlaceholderHelper.php` - Blade template helpers
- `app/View/Components/UserAvatar.php` - User avatar component

### Frontend Assets
- `public/css/fixes.css` - Updated with placeholder styles
- `public/js/asset-fallbacks.js` - Enhanced JavaScript fallback system

### Blade Components
- `resources/views/components/user-avatar.blade.php` - User avatar component template

## Usage Examples

### 1. Blade Component (Recommended)
```blade
<!-- Simple user avatar -->
<x-user-avatar :user="$user" />

<!-- Custom size and class -->
<x-user-avatar :user="$user" :size="60" class="border" />

<!-- With initials disabled -->
<x-user-avatar :user="$user" :use-initials="false" />
```

### 2. Blade Directives
```blade
<!-- User avatar with automatic fallback -->
@userAvatar($user, 40, 'border')

<!-- Category image -->
@categoryImage($category, 150, 150, 'card-img-top')

<!-- Section image -->
@sectionImage($section, 200, 120, 'img-fluid')

<!-- Direct placeholder URL -->
<img src="@placeholder('user')" alt="User">
```

### 3. PHP Helper Methods
```php
use App\Helpers\ImageHelper;
use App\Helpers\PlaceholderHelper;

// Get user avatar URL with fallback
$avatarUrl = ImageHelper::getUserAvatar($user, 40);

// Generate initials avatar
$initialsUrl = ImageHelper::getInitialsAvatar('John Doe', 40);

// Get placeholder URL
$placeholderUrl = PlaceholderHelper::getPlaceholder('user');

// Generate complete HTML
$avatarHtml = PlaceholderHelper::userAvatarHtml($user, 40, 'rounded-circle');
```

### 4. JavaScript API
```javascript
// Generate initials avatar
const avatarUrl = window.ImageFallbacks.generateInitialsAvatar('John Doe', 40);

// Set user avatar with fallback
window.ImageFallbacks.setUserAvatar(imgElement, 'John Doe', 40);

// Set category image
window.ImageFallbacks.setCategoryImage(imgElement);

// Set section image
window.ImageFallbacks.setSectionImage(imgElement);
```

## Features

### Automatic Fallbacks
- Images automatically fall back to appropriate placeholders on error
- User avatars generate initials when names are available
- Consistent color generation based on user names
- Prevents infinite error loops

### Responsive Design
- SVG placeholders scale perfectly at any size
- CSS handles different screen sizes and dark mode
- Proper aspect ratios maintained

### Accessibility
- Proper alt text handling
- Focus states for interactive elements
- Screen reader friendly

### Performance
- Lightweight SVG placeholders
- Efficient JavaScript with mutation observers
- CSS-only fallbacks where possible

## Customization

### Colors
User avatar colors are generated from names using a predefined palette:
```php
$colors = [
    '#007bff', '#6f42c1', '#e83e8c', '#dc3545', '#fd7e14',
    '#ffc107', '#28a745', '#20c997', '#17a2b8', '#6c757d'
];
```

### Placeholder Images
Replace the SVG files in `public/images/placeholders/` to customize the default placeholders.

### CSS Styling
Modify `public/css/fixes.css` to adjust placeholder styling:
```css
.user-avatar {
    border-radius: 50%;
    object-fit: cover;
    background-color: #e9ecef;
}
```

## Browser Support
- Modern browsers with SVG support
- Fallback handling for older browsers
- Progressive enhancement approach

## Testing
Visit `/test-placeholders.html` to see the system in action and test all placeholder types.

## Migration from Old System
The new system is backward compatible. Existing images will continue to work, but will now have proper fallbacks when they fail to load.

## Troubleshooting

### Images Not Showing Placeholders
1. Check that JavaScript is enabled
2. Verify placeholder SVG files exist
3. Check browser console for errors

### Initials Not Generating
1. Ensure user names are properly set
2. Check that the `alt` attribute or `data-name` is present
3. Verify JavaScript fallback system is loaded

### Styling Issues
1. Check that `fixes.css` is loaded
2. Verify CSS classes are applied correctly
3. Check for conflicting styles