<?php

namespace Fastre\BudgetBundle\Entity;

/**
 * Description of AbstractEntity
 *
 * @author julienfastre
 */
abstract class AbstractNode {
    
    const KEY_CODE = 'code';
    
    const KEY_ENTITY = 'entity';
    
    const KEY_LABEL = 'label';
    
    abstract public static function getEntityValue();
    
    
}


