<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Consumer\Analysis;

use TYPO3Analysis\Consumer\ConsumerAbstract;

class GithubLinguist extends ConsumerAbstract {

    /**
     * Gets a description of the consumer
     *
     * @return string
     */
    public function getDescription() {
        return 'Executes the Github Linguist analysis on a given folder and stores the results in linguist database table.';
    }

    /**
     * Initialize the consumer.
     * Sets the queue and routing key
     *
     * @return void
     */
    public function initialize() {
        $this->setQueue('analysis.linguist');
        $this->setRouting('analysis.linguist');
    }

    /**
     * The logic of the consumer
     *
     * @param \stdClass     $message
     * @return void
     */
    public function process($message) {
        $this->setMessage($message);
        $messageData = json_decode($message->body);

        $this->getLogger()->info('Receiving message', (array) $messageData);

        // If there is no directory to analyse, exit here
        if (is_dir($messageData->directory) !== true) {
            $this->getLogger()->critical('Directory does not exist', array('directory' => $messageData->directory));
            $this->acknowledgeMessage($this->getMessage());
            return;
        }

        try {
            $this->clearLinguistRecordsFromDatabase($messageData->versionId);
        } catch(\Exception $e) {
            $this->acknowledgeMessage($message);
            return;
        }

        $config = $this->getConfig();
        $workingDir = $config['Application']['GithubLinguist']['WorkingDir'];
        chdir($workingDir);

        // Execute github-linguist
        $dirToAnalyze = rtrim($messageData->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $command = 'bundle exec linguist ' . escapeshellarg($dirToAnalyze);

        $this->getLogger()->info('Analyze with github-linguist', array('directory' => $dirToAnalyze));

        try {
            $output = $this->executeCommand($command);
        } catch (\Exception $e) {
            $this->acknowledgeMessage($this->getMessage());
            return;
        }

        if ($output === array()) {
            $msg = 'github-linguist returns no result';
            $this->getLogger()->critical($msg);
            $this->acknowledgeMessage($this->getMessage());
            return;
        }

        $parsedResults = $this->parseGithubLinguistResults($output);

        // Store the github linguist results
        try {
            $this->storeLinguistDataInDatabase($messageData->versionId, $parsedResults);
        } catch (\Exception $e) {
            $this->acknowledgeMessage($message);
            return;
        }

        $this->acknowledgeMessage($message);
        $this->getLogger()->info('Finish processing message', (array) $messageData);
    }

    /**
     * Parse the GithubLinguist results.
     *
     * $results can have a look like this:
     *      array(4) {
     *          [0]=> string(11) "87.58%  PHP"
     *          [1]=> string(18) "12.25%  JavaScript"
     *          [2]=> string(12) "0.16%   XSLT"
     *          [3]=> string(13) "0.01%   Shell"
     *      }
     *
     * @param array $results
     * @return array
     */
    protected function parseGithubLinguistResults(array $results) {
        $parsedResults = array();

        foreach ($results as $line) {
            // Formats a string from "87.58%  PHP" to "87.58" and "PHP"
            $parts = explode(' ', $line);

            $percent = str_replace(array('%', ' '), '', array_shift($parts));
            $language = array_pop($parts);
            $language = trim($language);
            $parsedResults[] = array(
                'percent' => $percent,
                'language' => $language
            );
        }

        return $parsedResults;
    }

    /**
     * Inserts the github-linguist results in database
     *
     * @param integer   $versionId
     * @param array     $result
     * @throws \Exception
     */
    protected function storeLinguistDataInDatabase($versionId, array $result) {
        $this->getLogger()->info('Store linguist information in database', array('version' => $versionId));
        foreach ($result as $language) {
            $language['version'] = $versionId;
            $insertedId = $this->getDatabase()->insertRecord('linguist', $language);

            if (!$insertedId) {
                $this->getLogger('Insert of language failed', $language);
                throw new \Exception('Insert of language failed', 1368805993);
            }
        }
    }

    /**
     * Deletes the linguist records of github linguist analyses
     *
     * @param integer   $versionId
     * @return void
     * @throws \Exception
     */
    protected function clearLinguistRecordsFromDatabase($versionId) {
        $deleteResult = $this->getDatabase()->deleteRecords('linguist', array('version' => intval($versionId)));

        if ($deleteResult === false) {
            $msg = 'Delete of inguist records for version failed';
            $this->getLogger()->critical($msg, array('version' => $versionId));

            $msg = sprintf('Delete of inguist records for version %s failed', $versionId);
            throw new \Exception($msg, 1368805543);
        }
    }
}