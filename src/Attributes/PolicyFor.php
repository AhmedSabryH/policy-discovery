<?php

namespace MudQadm\PolicyDiscovery\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class PolicyFor
{
    /**
     * @param array<class-string>|string $models
     */
    public function __construct(public array|string $models)
    {
        if (is_string($this->models)) {
            $this->models = [$this->models];
        }
    }
}
