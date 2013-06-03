<?php

namespace Fastre\BudgetBundle\Entity;

/**
 * 
 *
 * @author Julien Fastré <julien arobase fastre point info>
 */
class CategoryEconomic extends AbstractNode {
    
    const VALUE_TYPE = 'economic_code';
    
    const KEY_CODE_STRING = 'code_string';
    
    
    
    public static function getEntityValue() {
        return self::VALUE_TYPE;
    }    
}

