<?php

namespace Fastre\BudgetBundle\Entity;

/**
 * Description of CategoryFonction
 *
 * @author Julien Fastré <julien arobase fastre point info>
 */
class CategoryFonction  extends AbstractNode {
    
   const VALUE_TYPE = 'fcode';
   
   
    
    
   public static function getEntityValue() {
        return self::VALUE_TYPE;
    }    
}

