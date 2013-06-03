<?php

namespace Fastre\BudgetBundle\Entity;

/**
 * Description of Entry
 *
 * @author Julien Fastré <julien arobase fastre point info>
 */
class Entry extends AbstractNode {
    
    const VALUE_TYPE = 'entry';
    
    const KEY_LABEL = 'label';
    
    const KEY_CODE_ECONOMIC = 'code_economique';
    
    const KEY_CODE_FONCTION = 'code_fonctionnel';
    
    const KEY_INDEX = 'indice';
    
    const KEY_YEAR = 'year';
    
    const KEY_AMOUNT = 'amount';
    
    
    
    public static function getEntityValue() {
        return self::VALUE_TYPE;
    }    
}

