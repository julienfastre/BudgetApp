<?php

namespace Fastre\BudgetBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Fastre\BudgetBundle\Entity\Entry;

/**
 * 
 *
 * @author Julien Fastré <julien arobase fastre point info>
 */
class ImportCommand extends ContainerAwareCommand {
    
    const ARGUMENT_FILE = 'file';
    
    const ARGUMENT_CITY = 'ville';

    
    protected function configure()
    {
        $this
            ->setName('budget:import')
            ->setDescription('import budget from csv')
            ->addArgument(self::ARGUMENT_CITY, InputArgument::REQUIRED, 'city concerned')
            ->addArgument(self::ARGUMENT_FILE, InputArgument::REQUIRED, 'csv file to import')
                ;
        
    }
    
    protected function execute(InputInterface $input, OutputInterface $output) {
        
        
        $client = $this->getContainer()->get('neo4jclient');

        $ville = $client->makeNode();

        $ville->setProperty(Entry::KEY_TYPE, 'liege')
                  ->setProperty('name', $input->getArgument(self::ARGUMENT_CITY));

        $ville->save();
        
        $row = 1;
        if (($handle = fopen($input->getArgument(self::ARGUMENT_FILE), "r")) !== FALSE) {
            
            for ($i = 0; $i < 30; $i++) {
                
                $data = fgetcsv($handle, 1000, ",");
                
                $num = count($data);
                echo "$num champs à la ligne $i: \n";
                
                
                $entry = $client->makeNode();
                
                $entry->setProperty(Entry::KEY_TYPE, Entry::VALUE_TYPE)
                        ->setProperty(Entry::KEY_LABEL, $data[8]);

                $entry->relateTo($ville, 'has');
                
                $entry->save();
                
                
                
            }
            
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                
            }
        
         fclose($handle);
        
        }
    }
    
}

