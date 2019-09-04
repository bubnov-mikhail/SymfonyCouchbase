<?php

namespace Apperturedev\CouchbaseBundle\Command;

use Apperturedev\CouchbaseBundle\Classes\CouchbaseORM;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CouchbaseGenerateIndexCommand extends Command
{
    /**
     * @var CouchbaseORM
     */
    protected $couchbase;

    /**
     * CouchbaseGenerateIndexCommand constructor.
     *
     * @param CouchbaseORM $couchbase
     */
    public function __construct(CouchbaseORM $couchbase)
    {
        parent::__construct();
        $this->couchbase = $couchbase;
    }

    protected function configure()
    {
        $this
            ->setName('couchbase:generate:index')
            ->setDescription(
                'Generates primary index'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Generating index');
        try {
            $this->couchbase->addPrimaryIndex();
            $output->writeln('<success>Success</success>');
        } catch (\Exception $e) {
            $output->writeln('<error>Success</error>');
        }
    }
}
