<?php

namespace Geocoder\Provider;

use DataDogStatsD;
use Geocoder\Exception\InvalidCredentialsException;
use Geocoder\Exception\ChainNoResultException;

/**
 * Override the default Chain Provider
 * The only reason we do this is in order to be able to read
 * the name of the provider that fetched the result.
 *
 * @author Andreas Kristiansen <mail@ankr.dk>
 */
class BowntyChainProvider implements ProviderInterface {

    /**
     * @var ProviderInterface[]
     */
    protected $providers = array();

    /**
     * Constructor
     *
     * @param ProviderInterface[] $providers
     */
    public function __construct(array $providers = array())
    {
        $this->providers = $providers;
    }

    /**
     * Add a provider
     *
     * @param ProviderInterface $provider
     */
    public function addProvider(ProviderInterface $provider)
    {
        $this->providers[] = $provider;
    }

    /**
     * {@inheritDoc}
     */
    public function getGeocodedData($address)
    {
        $exceptions = array();
        foreach ($this->providers as $provider) {
            $timer = microtime(true);
            $success = false;
            try {
                $result = $provider->getGeocodedData($address);
                if ($result) {
                    $success = true;
                }
                return $result;
            } catch (InvalidCredentialsException $e) {
                throw $e;
            } catch (\Exception $e) {
                $exceptions[] = $e;
            } finally {
                $tags = ['provider' => $provider->getName(), 'type' => 'redirect', 'success' => $success];
                DataDogStatsD::timing('geo.lookup', (microtime(true) - $timer), 1, $tags);
                DataDogStatsD::increment('geo.lookup', 1, $tags);
            }
        }
        throw new ChainNoResultException(sprintf('No provider could provide the address "%s"', $address), $exceptions);
    }

    /**
     * {@inheritDoc}
     */
    public function getReversedData(array $coordinates)
    {
        $exceptions = array();
        foreach ($this->providers as $provider) {
            try {
                return $provider->getReversedData($coordinates);
            } catch (InvalidCredentialsException $e) {
                throw $e;
            } catch (\Exception $e) {
                $exceptions[] = $e;
            }
        }
        throw new ChainNoResultException(sprintf('No provider could provide the coordinated %s', json_encode($coordinates)), $exceptions);
    }

    /**
     * {@inheritDoc}
     */
    public function setMaxResults($limit)
    {
        foreach ($this->providers as $provider) {
            $provider->setMaxResults($limit);
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'bownty_chain';
    }

}
