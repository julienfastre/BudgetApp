<?php

namespace Fastre\BudgetBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Fastre\BudgetBundle\Entity\Entry;
use Fastre\BudgetBundle\Entity\City;
use Fastre\BudgetBundle\Entity\AbstractNode;
use Fastre\BudgetBundle\Entity\CategoryFonction;
use Fastre\BudgetBundle\Entity\Relations\HasCategory;
use Everyman\Neo4j\Cypher\Query;
use Fastre\BudgetBundle\Entity\Budget;
use Fastre\BudgetBundle\Entity\Service;
use Fastre\BudgetBundle\Entity\JoinCategoryFonction;
use Fastre\BudgetBundle\Entity\Relations\HasRootCategory;
use Fastre\BudgetBundle\Entity\Relations\IsEqual;

/**
 * 
 *
 * @author Julien Fastré <julien arobase fastre point info>
 */
class ImportCommand extends ContainerAwareCommand {
    
    const ARGUMENT_FILE = 'file';
    
    const ARGUMENT_CITY = 'ville';
    
    const ARGUMENT_SLUG = 'slug_de_la_ville';
    
    /**
     *
     * @var \Everyman\Neo4j\Client
     */
    private $c;
    
    
    private $currentCity;

    
    protected function configure()
    {
        $this
            ->setName('budget:import')
            ->setDescription('import budget from csv')
            ->addArgument(self::ARGUMENT_CITY, InputArgument::REQUIRED, 'city concerned')
            ->addArgument(self::ARGUMENT_FILE, InputArgument::REQUIRED, 'csv file to import')
            ->addArgument(self::ARGUMENT_SLUG, InputArgument::REQUIRED, 'Slug de la ville')
                ;
        
    }
    
    protected function execute(InputInterface $input, OutputInterface $output) {
        
        
        $client = $this->getContainer()->get('neo4jclient');
        
        $this->c = $client;
        
        //try to get the city
        //TODO

        $ville = $client->makeNode();

        $ville->setProperty(AbstractNode::KEY_ENTITY, City::getEntityValue())
                  ->setProperty(AbstractNode::KEY_LABEL, $input->getArgument(self::ARGUMENT_CITY))
                  ->setProperty(AbstractNode::KEY_CODE, $input->getArgument(self::ARGUMENT_SLUG))
                ;

        $ville->save();
        
        $this->currentCity = $ville;
        
        $rootFcode = $this->getOrCreateRootFCode($ville);
        
        
        
        $budget = $client->makeNode()
                ->setProperty(Budget::KEY_ENTITY, Budget::getEntityValue())
                ->setProperty(Budget::KEY_CODE, '2012_CMB_2')
                ->setProperty(Budget::KEY_LABEL, '2012 - 2ième cahier de modification budgétaire')
                ->setProperty(Budget::KEY_YEAR, 2012)
                ->save();
        
        $ville->relateTo($budget, 'VOTE')
                ->save();
        
        $ordinary = $client->makeNode()
                ->setProperty(Service::KEY_ENTITY, Service::getEntityValue())
                ->setProperty(Service::KEY_CODE, '2012_CMB_2_ORDINARY')
                ->setProperty(Service::KEY_TYPE, Service::VALUE_TYPE_ORDINARY)
                ->save();
        
        $budget->relateTo($ordinary, 'HAS_SERVICE')
                ->save();
        
        $this->createRootFunctionalCategories($ordinary, $budget, $ville);
        
        $extraordinary = $client->makeNode()
                ->setProperty(Service::KEY_ENTITY, Service::getEntityValue())
                ->setProperty(Service::KEY_CODE, '2012_CMB_2_EXTRAORDINARY')
                ->setProperty(Service::KEY_TYPE, Service::VALUE_TYPE_EXTRAORDINARY)
                ->save();
        
        $budget->relateTo($extraordinary, 'HAS_SERVICE')
                ->save();
        
        $this->createRootFunctionalCategories($extraordinary, $budget, $ville);
        
        $ordinaryCR = array('60', '61', '62', '68', '70', '71', '72', '78', '7x');
        $extraordinaryCR = array('80', '81', '82', '88', '90', '91', '92', '98');
        
        
        

        if (($handle = fopen($input->getArgument(self::ARGUMENT_FILE), "r")) !== FALSE) {
            
            $data = fgetcsv($handle, 1000, ","); //pass the first line, heading of the file
            
              $i = 1;
//            for ($i = 0; $i < 200; $i++) {
              while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {  
                $data = fgetcsv($handle, 1000, ",");
                
                $num = count($data);
                echo "$num champs à la ligne $i: \n";
                
                
                $entry = $client->makeNode();
                
                $entry->setProperty(Entry::KEY_ENTITY, Entry::getEntityValue())
                        ->setProperty(Entry::KEY_LABEL, $data[8])
                        ->setProperty(Entry::KEY_AMOUNT, (double) $data[15]);
                
                //add indice
                $econ = str_split($data[3]);
                if ($econ[1] == 0) {
                    $indice = 0;
                } elseif ($econ[1] <= 5 ) {
                    $indice = -1;
                } elseif ($econ[1] > 5) {
                    $indice = 1;
                } else {
                    throw new \Exception('bad economical code');
                }
                
                $entry->setProperty(Entry::KEY_INDEX, $indice);
                $entry->setProperty(Entry::KEY_CR, $data[5]);
                
                
                $entry->save();
                
                if (in_array($data[6], $ordinaryCR)) {
                    $service = $ordinary;
                    
                } elseif (in_array($data[6], $extraordinaryCR)) {
                    $service = $extraordinary;
                } else {
                    throw new \Exception('CR code does not match, catched '.$data[6]);
                }
                
                $catF = $this->getOrCreateCategory($service, $budget, $ville, $data[2]);
                
                $catF->relateTo($entry, 'own')
                        ->save();
                
                
                
               $i++; 
               
               if ($i > 500) {
                   break;
               } 
            }
        
         fclose($handle);
        
        }
    }
    
    
    private function getRootCategories() {
        return array(
            '0' => 'Non imputable',
            '1' => 'Administration générale',
            '3' => 'Ordre et sécurité publique',
            '4' => 'Communications',
            '5' => 'Commerce, industrie',
            '6' => 'Agriculture',
            '7' => 'Enseignement',
            '8' => 'Interventions sociales et santé publique',
            '9' => 'Logements sociaux et aménagement du territoire'
        );
    }
    
    
    private function createRootFunctionalCategories($service, $budget, $city) {
        $a = $this->getRootCategories();
        
        foreach($a as $key => $label) {
            $n = $this->c->makeNode(array(
                CategoryFonction::KEY_ENTITY => CategoryFonction::getEntityValue(),
                CategoryFonction::KEY_CODE => (string) $key,
                CategoryFonction::KEY_LABEL, $label
            ))
                    ->save();
            
            $service->relateTo($n, HasCategory::getName())
                    ->save();
            
            echo "key est $key \n";
            $join = $this->getOrCreateJoinCategoryFunctional($key, $city);
            
            $join->setProperty(JoinCategoryFonction::KEY_LABEL, $label)
                    ->save();
            
            $n->relateTo($join, IsEqual::getName())
                    ->setProperty('service_type' , $service->getProperty(Service::KEY_TYPE))
                    ->setProperty('budget', $budget->getProperty(Budget::KEY_CODE))
                    ->setProperty('city' , $city->getProperty(City::KEY_CODE))
                    ->save();
        }
        
    }
    
    private $proxyCategory = array();
    
    private function getOrCreateCategory($service, $budget, $city, $code, $row = 'last') {
        
        if ($code === '000') {
            $code = '0';
        }
        
        
        
        if (isset($this->proxyCategory[$service->getId()][$code])) {
            return $this->proxyCategory[$service->getId()][$code];
        }
        
        $a = str_split($code);
        
        if ($row == 'last') {
            $row = count($a);
        }
        
        $preparedCode = '';
        for ($i = 0; $i < $row; $i++) {
            
            $preparedCode.= $a[$i];
            
        }
        
        if ($preparedCode == '') {
            throw new \Exception("code must not be empty ! 258");
        }
        
        $queryString = 'START n=node('.$service->getId().') 
            MATCH n-['.HasCategory::getName().'*1..6]->x 
            WHERE x.entity=\''.CategoryFonction::getEntityValue().'\' AND x.'.CategoryFonction::KEY_CODE.' = \''.$preparedCode.'\'
            RETURN x';
        
        echo 'Prepare Query '.$queryString."\n";
        
        $query = new Query($this->c, $queryString);
        
        $result = $query->getResultSet();
        
        echo $result->count()." Résultats \n";
        
        if ($result->count() === 1 ) {
            $this->proxyCategory[$service->getId()][$code] = $result[0]['x'];
            return $result[0]['x'];
        } elseif($result->count() > 1) {
            throw new \Exception('Results should not be more than one...');
        } elseif($result->count() === 0) {
            
            $parentCode = '';
            for ($i = 0; $i < $row-1; $i++) {
                $parentCode.= $a[$i];
            }
            
            $parent = $this->getOrCreateCategory($service, $budget, $city, $parentCode);
            
            $child = $this->c->makeNode(array(
                CategoryFonction::KEY_ENTITY => CategoryFonction::getEntityValue(),
                CategoryFonction::KEY_CODE => $preparedCode,
                CategoryFonction::KEY_LABEL => '',
            ))
                    ->save();
            
            
            $join = $this->getOrCreateJoinCategoryFunctional($preparedCode, $city);
            
            $child->relateTo($join, IsEqual::getName())
                    ->setProperty('service_type' , $service->getProperty(Service::KEY_TYPE))
                    ->setProperty('budget', $budget->getProperty(Budget::KEY_CODE))
                    ->setProperty('city' , $city->getProperty(City::KEY_CODE))
                    ->save();
            
            $parent->relateTo($child, HasCategory::getName())
                    ->save();
            
            $this->proxyCategory[$service->getId()][$code] = $child;
            
            return $child;
        }
        
    }
    
    
    private $proxyJoinCategory;
    
    private function getOrCreateJoinCategoryFunctional($code, $ville, $row = 'last') {
        
        echo "code est lg 320 : $code \n";
        
        if ($code === '000') {
            $code = '0';
        }
        
        if ($code === '') {
            throw new \Exception("code must not be empty !326 ");
        }
        
        if (isset($this->proxyJoinCategory[$code])) {
            return $this->proxyJoinCategory[$code];
        }
        
        $rfc = $this->getOrCreateRootFCode($ville);
        
        $a = str_split($code);
        
        if ($row == 'last') {
            $row = count($a);
        }
        
        $preparedCode = '';
        for ($i = 0; $i < $row; $i++) {
            
            $preparedCode.= $a[$i];
            
        }
        
        if ($preparedCode == '') {
            throw new \Exception("code must not be empty ! ");
        }
        
        $queryString = 'START n=node('.$rfc->getId().') 
            MATCH n-['.HasCategory::getName().'*1..6]->x 
            WHERE x.entity=\''.JoinCategoryFonction::getEntityValue().'\' AND x.'.CategoryFonction::KEY_CODE.' = \''.$preparedCode.'\'
            RETURN x';
        
        echo 'Prepare Query '.$queryString."\n";
        
        $query = new Query($this->c, $queryString);
        
        $result = $query->getResultSet();
        
        echo $result->count()." Résultats \n";
        
        if ($result->count() === 1 ) {
            $this->proxyJoinCategory[$code] = $result[0]['x'];
            return $result[0]['x'];
        } elseif($result->count() > 1) {
            throw new \Exception('Results should not be more than one...');
        } elseif($result->count() === 0) {
            
            $parentCode = '';
            for ($i = 0; $i < $row-1; $i++) {
                $parentCode.= $a[$i];
            }
            
            $parent = $this->getOrCreateJoinCategoryFunctional($parentCode, $ville);
            
            $child = $this->c->makeNode(array(
                JoinCategoryFonction::KEY_ENTITY => CategoryFonction::getEntityValue(),
                JoinCategoryFonction::KEY_CODE => (string) $preparedCode,
                JoinCategoryFonction::KEY_LABEL => '',
            ))
                    ->save();
            
            $parent->relateTo($child, HasCategory::getName())
                    ->save();
            
            $this->proxyJoinCategory[$code] = $child;
            
            return $child;
        }
        
    }
    
    private $proxyRootFCode = null;

    public function getOrCreateRootFCode(\Everyman\Neo4j\Node $ville) {
        
        if ($this->proxyRootFCode !== null) {
            return $this->proxyRootFCode;
        }
        
        $queryString = 'START n=node('.$ville->getId().') MATCH n-[:'.
            HasRootCategory::getName().']->c RETURN c';
        
        $q = new Query($this->c, $queryString);
        
        $result = $q->getResultSet();
        
        if ($result->count() === 1) {
            $this->proxyRootFCode = $result[0]['c'];
            return $this->proxyRootFCode;
        } else {
            //create RootFCode node
            $rFc = $this->c->makeNode(array(
                \Fastre\BudgetBundle\Entity\RootCategoryFonction::KEY_ENTITY => \Fastre\BudgetBundle\Entity\RootCategoryFonction::getEntityValue(),
                \Fastre\BudgetBundle\Entity\RootCategoryFonction::KEY_CODE => $ville->getProperty(City::KEY_CODE)
            ))
                    ->save();
            
            $ville->relateTo($rFc, HasRootCategory::getName())
                    ->save();
            
            $this->proxyRootFCode = $rFc;
            
            
            
            //create first childs:
            
            $a = $this->getRootCategories();
            
            foreach ($a as $key => $label) {
                $jc = $this->c->makeNode(array(
                    JoinCategoryFonction::KEY_ENTITY => JoinCategoryFonction::getEntityValue(),
                    JoinCategoryFonction::KEY_CODE => (string) $key,
                    JoinCategoryFonction::KEY_LABEL => $label
                ))
                        ->save();
                
                $rFc->relateTo($jc, HasCategory::getName())
                        ->save();
            }
            
            return $this->proxyRootFCode;
            
        }
    }
    
    

    
    
    
}

