<?php


namespace Mailvan\Client\Mailchimp;

use Guzzle\Common\Collection;
use Guzzle\Service\Description\ServiceDescription;
use Mailvan\Core\Client as BaseClient;
use Mailvan\Core\Model\SubscriptionListInterface;
use Mailvan\Core\Model\UserInterface;

class Client extends BaseClient
{
    /**
     * Factory method.
     *
     * @param array $config
     * @return \Guzzle\Service\Client|void
     */
    public static function factory($config = array())
    {
        $required_params = array('base_url', 'api_key');

        $config = Collection::fromConfig($config, array(), $required_params);

        $api_key = $config->get('api_key');
        $dc = substr($api_key, strrpos($api_key, '-')+1);

        $config->set('base_url', str_replace('%%dc%%', $dc, $config->get('base_url')));

        $client = new self($config->get('base_url'), $config);

        $client->setDescription(ServiceDescription::factory($config->get('operations') ?: dirname(__FILE__).'/operations.json'));
    }

    /**
     * @param $command
     * @param $params
     * @param callable $responseParser
     * @return mixed
     * @throws MailchimpException
     */
    private function doExecuteCommand($command, $params, \Closure $responseParser)
    {
        $params['api_key'] = $this->getConfig('api_key');

        $response = $this->getCommand($command, $params)->getResult();
        if (empty($response['status']) || $response['status'] != 'error') {
            return $responseParser($response);
        }

        throw new MailchimpException($response['error'], $response['code']);
    }


    /**
     * Subscribes given user to given SubscriptionList. Returns true if operation is successful
     *
     * @param UserInterface $user
     * @param SubscriptionListInterface $list
     * @throws MailchimpException
     * @return boolean
     */
    public function subscribe(UserInterface $user, SubscriptionListInterface $list)
    {
        $params = array(
            'email' => array('email' => $user->getEmail()),
            'id' => $list->getId(),
            'merge_vars' => array(
                'FNAME' => $user->getFirstName(),
                'LNAME' => $user->getLastName(),
                'OPTIN_TIME' => gmdate('Y-m-d H:i:s'),
            )
        );

        return $this->doExecuteCommand('subscribe', $params, function() {
            return true;
        });
    }

    /**
     * Unsubscribes given user from given SubscriptionList.
     *
     * @param UserInterface $user
     * @param SubscriptionListInterface $list
     * @return boolean
     */
    public function unsubscribe(UserInterface $user, SubscriptionListInterface $list)
    {
        return $this->doExecuteCommand(
            'unsubscribe',
            array('id' => $list->getId(), 'email' => array('email' => $user->getEmail())),
            function($response) {
                return $response['complete'];
            }
        );
    }

    /**
     * Moves user from one list to another. In some implementation can create several http queries.
     *
     * @param UserInterface $user
     * @param SubscriptionListInterface $from
     * @param SubscriptionListInterface $to
     * @return boolean
     */
    public function move(UserInterface $user, SubscriptionListInterface $from, SubscriptionListInterface $to)
    {
        return $this->unsubscribe($user, $from) && $this->subscribe($user, $to);
    }

    /**
     * Returns list of subscription lists created by owner.
     *
     * @return array
     */
    public function getLists()
    {
        return $this->doExecuteCommand('getLists', array(), function($response) {
            return array_map(
                function($item) {
                    return $this->createSubscriptionList($item['id']);
                },
                $response['data']
            );
        });
    }
}