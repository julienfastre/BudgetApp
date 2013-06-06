<?php

namespace Fastre\BudgetBundle\Entity;

/**
 * Description of Service
 *
 * @author julienfastre
 */
class Service extends AbstractNode {
    
    const VALUE_TYPE = 'service';
    
    const KEY_TYPE = 'type';
    
    const VALUE_TYPE_ORDINARY = 1;
    const VALUE_TYPE_EXTRAORDINARY = 0;
    
    public static function getEntityValue() {
        return self::VALUE_TYPE;
    }
    
}


