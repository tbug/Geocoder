<?php

namespace Geocoder\Provider;

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
    private $providers = array();

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
            try {
                $results = $provider->getGeocodedData($address);
                foreach ($results as &$result) {
                    $result['__provider_name'] = $provider->getName();
                }
                return $results;
            } catch (InvalidCredentialsException $e) {
                throw $e;
            } catch (\Exception $e) {
                $exceptions[] = $e;
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
