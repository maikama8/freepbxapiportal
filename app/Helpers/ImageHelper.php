<?php

namespace App\Helpers;

class ImageHelper
{
    /**
     * Get user avatar with fallback to default placeholder
     */
    public static function getUserAvatar($user = null, $size = 100)
    {
        // If user has avatar, try to use it
        if ($user && isset($user->avatar) && !empty($user->avatar)) {
            $avatarPath = public_path('storage/avatars/' . $user->avatar);
            if (file_exists($avatarPath)) {
                return asset('storage/avatars/' . $user->avatar);
            }
        }
        
        // Check for Sneat theme avatars
        $sneatAvatars = [
            'sneat/img/avatars/1.png',
            'sneat/img/avatars/2.png',
            'sneat/img/avatars/3.png',
            'sneat/img/avatars/4.png',
            'sneat/img/avatars/5.png'
        ];
        
        foreach ($sneatAvatars as $avatar) {
            if (file_exists(public_path($avatar))) {
                return asset($avatar);
            }
        }
        
        // Return default user placeholder
        return asset('images/placeholders/user-default.svg');
    }
    
    /**
     * Get category image with fallback
     */
    public static function getCategoryImage($category = null)
    {
        if ($category && isset($category->image) && !empty($category->image)) {
            $imagePath = public_path('storage/categories/' . $category->image);
            if (file_exists($imagePath)) {
                return asset('storage/categories/' . $category->image);
            }
        }
        
        return asset('images/placeholders/category-default.svg');
    }
    
    /**
     * Get section image with fallback
     */
    public static function getSectionImage($section = null)
    {
        if ($section && isset($section->image) && !empty($section->image)) {
            $imagePath = public_path('storage/sections/' . $section->image);
            if (file_exists($imagePath)) {
                return asset('storage/sections/' . $section->image);
            }
        }
        
        return asset('images/placeholders/section-default.svg');
    }
    
    /**
     * Get generic placeholder based on type
     */
    public static function getPlaceholder($type = 'user', $size = 100)
    {
        $placeholders = [
            'user' => 'images/placeholders/user-default.svg',
            'category' => 'images/placeholders/category-default.svg',
            'section' => 'images/placeholders/section-default.svg',
        ];
        
        $placeholder = $placeholders[$type] ?? $placeholders['user'];
        return asset($placeholder);
    }
    
    /**
     * Generate initials-based avatar URL
     */
    public static function getInitialsAvatar($name, $size = 100, $bgColor = null, $textColor = 'white')
    {
        if (empty($name)) {
            return self::getPlaceholder('user', $size);
        }
        
        $initials = self::getInitials($name);
        $bgColor = $bgColor ?: self::generateColorFromName($name);
        
        // Create SVG with initials
        $svg = '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<circle cx="' . ($size/2) . '" cy="' . ($size/2) . '" r="' . ($size/2) . '" fill="' . $bgColor . '"/>';
        $svg .= '<text x="50%" y="50%" text-anchor="middle" dy="0.35em" font-family="Arial, sans-serif" font-size="' . ($size * 0.4) . '" fill="' . $textColor . '">' . $initials . '</text>';
        $svg .= '</svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
    
    /**
     * Extract initials from name
     */
    private static function getInitials($name)
    {
        $words = explode(' ', trim($name));
        $initials = '';
        
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper(substr($word, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }
        
        return $initials ?: 'U';
    }
    
    /**
     * Generate consistent color from name
     */
    private static function generateColorFromName($name)
    {
        $colors = [
            '#007bff', '#6f42c1', '#e83e8c', '#dc3545', '#fd7e14',
            '#ffc107', '#28a745', '#20c997', '#17a2b8', '#6c757d'
        ];
        
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    }
}