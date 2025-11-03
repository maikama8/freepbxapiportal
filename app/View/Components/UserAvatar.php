<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\Helpers\ImageHelper;

class UserAvatar extends Component
{
    public $user;
    public $size;
    public $class;
    public $useInitials;
    
    public function __construct($user = null, $size = 40, $class = '', $useInitials = true)
    {
        $this->user = $user;
        $this->size = $size;
        $this->class = $class;
        $this->useInitials = $useInitials;
    }
    
    public function render()
    {
        return view('components.user-avatar');
    }
    
    public function getAvatarUrl()
    {
        if ($this->useInitials && $this->user && isset($this->user->name)) {
            // Try to get actual avatar first
            $avatarUrl = ImageHelper::getUserAvatar($this->user, $this->size);
            
            // If it's the default placeholder and we have a name, use initials
            if (str_contains($avatarUrl, 'user-default.svg') && !empty($this->user->name)) {
                return ImageHelper::getInitialsAvatar($this->user->name, $this->size);
            }
            
            return $avatarUrl;
        }
        
        return ImageHelper::getUserAvatar($this->user, $this->size);
    }
}