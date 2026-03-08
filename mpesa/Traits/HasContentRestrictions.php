<?php

namespace App\Traits;


trait HasContentRestrictions {
    public function canAccessBooks(): bool
    {
        return $this->hasActivePlan('premium');
    }

    public function canAccessAllContent(): bool
    {
        return $this->hasActivePlan('premium');
    }

    public function getContentAccess(): array
    {
        $plan = $this->currentPlan();

        if(!$plan){
            return [
                'books' => false,
                'podcasts' => true, // Always free
            ];
        }

        return match($plan->slug){
            'premium' => [
                'books' => true,
                'podcasts' => true,
            ],
            default => [
                'books' => false,
                'podcasts' => true, // Always free
            ]
        };
    }
}
