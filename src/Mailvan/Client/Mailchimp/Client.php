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
    public static function factory($config = [])
    {
        $required_params = ['base_url', 'api_key'];

        $config = Collection::fromConfig($config, [], $required_params);

        $api_key = $config->get('api_key');
        $dc = substr($api_key, strrpos($api_key, '-')+1);

        $config->set('base_url', str_replace('%%dc%%', $dc, $config->get('base_url')));

        $client = new self($config->get('base_url'), $config);

        $client->setDescription(ServiceDescription::factory($config->get('operations') ?: dirname(__FILE__).'/operations.json'));
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
        $params = [
            'email' => ['email' => $user->getEmail()],
            'id' => $list->getId(),
            'merge_vars' => [ 'FNAME' => $user->getFirstName(), 'LNAME' => $user->getLastName(), 'OPTIN_TIME' => gmdate('Y-m-d H:i:s') ]
        ];

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
            ['id' => $list->getId(), 'email' => array('email' => $user->getEmail())],
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
        return $this->doExecuteCommand('getLists', [], function($response) {
            return array_map(
                function($item) {
                    return $this->createSubscriptionList($item['id']);
                },
                $response['data']
            );
        });
    }

    /**
     * @param $params
     * @return mixed
     */
    protected function mergeApiKey($params)
    {
        $params['api_key'] = $this->getConfig('api_key');
        return $params;
    }

    /**
     * @param $response
     * @return bool
     */
    protected function hasError($response)
    {
        return isset($response['status']) && $response['status'] == 'error';
    }

    /**
     * @param $response
     * @return MailchimpException
     */
    protected function raiseError($response)
    {
        return new MailchimpException($response['error'], $response['code']);
    }
}