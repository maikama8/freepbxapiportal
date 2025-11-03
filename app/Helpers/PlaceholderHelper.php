<?php

namespace App\Helpers;

class PlaceholderHelper
{
    /**
     * Get placeholder image URL by type
     */
    public static function getPlaceholder($type = 'user')
    {
        $placeholders = [
            'user' => asset('images/placeholders/user-default.svg'),
            'category' => asset('images/placeholders/category-default.svg'),
            'section' => asset('images/placeholders/section-default.svg'),
        ];
        
        return $placeholders[$type] ?? $placeholders['user'];
    }
    
    /**
     * Get user avatar with automatic fallback
     */
    public static function userAvatar($user = null, $size = 40)
    {
        return ImageHelper::getUserAvatar($user, $size);
    }
    
    /**
     * Get initials avatar data URL
     */
    public static function initialsAvatar($name, $size = 40, $bgColor = null)
    {
        return ImageHelper::getInitialsAvatar($name, $size, $bgColor);
    }
    
    /**
     * Generate HTML for user avatar with fallbacks
     */
    public static function userAvatarHtml($user = null, $size = 40, $class = '')
    {
        $src = self::userAvatar($user, $size);
        $alt = $user->name ?? 'User';
        $fallback = self::getPlaceholder('user');
        
        return sprintf(
            '<img src="%s" alt="%s" class="rounded-circle %s" width="%d" height="%d" style="object-fit: cover;" onerror="this.src=\'%s\'">',
            $src,
            htmlspecialchars($alt),
            $class,
            $size,
            $size,
            $fallback
        );
    }
    
    /**
     * Generate HTML for category image with fallbacks
     */
    public static function categoryImageHtml($category = null, $width = 150, $height = 150, $class = '')
    {
        $src = ImageHelper::getCategoryImage($category);
        $alt = $category->name ?? 'Category';
        $fallback = self::getPlaceholder('category');
        
        return sprintf(
            '<img src="%s" alt="%s" class="category-image %s" width="%d" height="%d" style="object-fit: cover;" onerror="this.src=\'%s\'">',
            $src,
            htmlspecialchars($alt),
            $class,
            $width,
            $height,
            $fallback
        );
    }
    
    /**
     * Generate HTML for section image with fallbacks
     */
    public static function sectionImageHtml($section = null, $width = 150, $height = 150, $class = '')
    {
        $src = ImageHelper::getSectionImage($section);
        $alt = $section->name ?? 'Section';
        $fallback = self::getPlaceholder('section');
        
        return sprintf(
            '<img src="%s" alt="%s" class="section-image %s" width="%d" height="%d" style="object-fit: cover;" onerror="this.src=\'%s\'">',
            $src,
            htmlspecialchars($alt),
            $class,
            $width,
            $height,
            $fallback
        );
    }
}