<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class ListConsumerCommand extends Command {

    /**
     * Base namespace of consumer
     *
     * @var String
     */
    const BASE_NAMESPACE = 'TYPO3Analysis\Consumer\\';

    /**
     * Pad length for consumer name
     *
     * @var integer
     */
    const PAD_LENGTH = 30;

    /**
     * Path of consumer
     *
     * @var String
     */
    protected $consumerPath = null;

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure() {
        $this->setName('analysis:list-consumer')
             ->setDescription('Lists all available consumer');
    }

    /**
     * Sets the consumer path
     *
     * @param String    $consumerPath
     * @return void
     */
    public function setConsumerPath($consumerPath) {
        $this->consumerPath = $consumerPath;
    }

    /**
     * Gets the consumer path
     *
     * @return String
     */
    public function getConsumerPath() {
        return $this->consumerPath;
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * Sets up the consumer path
     *
     * @param InputInterface    $input      An InputInterface instance
     * @param OutputInterface   $output     An OutputInterface instance
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $consumerPath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Consumer';
        $consumerPath = realpath($consumerPath);
        $this->setConsumerPath($consumerPath);
    }

    /**
     * Executes the current command.
     *
     * Lists all available consumer.
     *
     * @param InputInterface    $input      An InputInterface instance
     * @param OutputInterface   $output     An OutputInterface instance
     * @return null|integer null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output) {

        $path = $this->getConsumerPath();
        $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        // Get all php files in given path
        $finder = new Finder();
        $finder->files()->in($path)->name('*.php')->notName('*Abstract.php')->notName('*Interface.php');

        $basePath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
        $basePath = realpath($basePath) . DIRECTORY_SEPARATOR;

        foreach ($finder as $file) {
            /* @var $file SplFileInfo */
            $className = $file->getRealpath();
            $className = str_replace($basePath, '', $className);
            $className = substr($className, 0, -4);
            $className = '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $className);

            // Initialize consumer and check if the parent class is ConsumerAbstract
            $consumer = new $className();
            $reflection = new \ReflectionClass($consumer);

            $parentClass = $reflection->getParentClass();
            if ($parentClass === false || $parentClass->getName() !== 'TYPO3Analysis\Consumer\ConsumerAbstract') {
                continue;
            }

            $consumerName = str_replace(self::BASE_NAMESPACE, '', $className);
            $consumerName = substr($consumerName, 1, strlen($consumerName) - 1);
            $consumerName = str_replace('\\', '\\\\', $consumerName);

            $message = str_pad($consumerName, self::PAD_LENGTH, ' ');
            $message = '<comment>' . $message . '</comment>';
            $message .= '<comment>' . $consumer->getDescription() . '</comment>';
            $output->writeln($message);
        }

        return null;
    }
}