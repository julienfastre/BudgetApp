<?php

namespace Fastre\BudgetBundle\Entity;

/**
 * Description of City
 *
 * @author julienfastre
 */
class City extends AbstractNode {
    
    const VALUE_TYPE = 'city';
    
    
    public static function getEntityValue() {
        return self::VALUE_TYPE;
    }    
}


