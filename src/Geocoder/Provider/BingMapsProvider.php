<?php

/**
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider;

use Geocoder\HttpAdapter\HttpAdapterInterface;
use Geocoder\Exception\InvalidCredentialsException;
use Geocoder\Exception\NoResultException;
use Geocoder\Exception\UnsupportedException;

/**
 * @author David Guyon <dguyon@gmail.com>
 */
class BingMapsProvider extends AbstractProvider implements LocaleAwareProviderInterface
{
    /**
     * @var string
     */
    const GEOCODE_ENDPOINT_URL = 'http://dev.virtualearth.net/REST/v1/Locations/?maxResults=%d&q=%s&key=%s';

    /**
     * @var string
     */
    const REVERSE_ENDPOINT_URL = 'http://dev.virtualearth.net/REST/v1/Locations/%F,%F?key=%s';

    /**
     * @var string
     */
    private $apiKey = null;

    /**
     * @var array
     */
    protected $options = array();

    /**
     * @param HttpAdapterInterface $adapter An HTTP adapter.
     * @param string               $apiKey  An API key.
     * @param string               $locale  A locale (optional).
     * @param array                $options Additional search options
     */
    public function __construct(HttpAdapterInterface $adapter, $apiKey, $locale = null, $options = array())
    {
        parent::__construct($adapter, $locale);

        $this->apiKey = $apiKey;
        $this->options = array_replace($this->options, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function getGeocodedData($address)
    {
        if (null === $this->apiKey) {
            throw new InvalidCredentialsException('No API Key provided');
        }

        // This API doesn't handle IPs
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedException('The BingMapsProvider does not support IP addresses.');
        }

        $query = sprintf(self::GEOCODE_ENDPOINT_URL, $this->getMaxResults(), urlencode($address), $this->apiKey);

        return $this->executeQuery($query);
    }

    /**
     * {@inheritDoc}
     */
    public function getReversedData(array $coordinates)
    {
        if (null === $this->apiKey) {
            throw new InvalidCredentialsException('No API Key provided');
        }

        $query = sprintf(self::REVERSE_ENDPOINT_URL, $coordinates[0], $coordinates[1], $this->apiKey);

        return $this->executeQuery($query);
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'bing_maps';
    }

    /**
     * @param string $query
     *
     * @return array
     */
    protected function executeQuery($query)
    {
        if (null !== $this->getLocale()) {
            $query = sprintf('%s&culture=%s', $query, str_replace('_', '-', $this->getLocale()));
        }

        if (count($this->options)) {
          $query .= '&' . http_build_query($this->options);
        }

        $content = $this->getAdapter()->getContent($query);

        if (null === $content) {
            throw new NoResultException(sprintf('Could not execute query %s', $query));
        }

        $json = json_decode($content);

        if (!isset($json->resourceSets[0]) || !isset($json->resourceSets[0]->resources)) {
            throw new NoResultException(sprintf('Could not execute query %s', $query));
        }

        $data = (array) $json->resourceSets[0]->resources;

        $results = array();

        foreach ($data as $item) {
            $coordinates = (array) $item->geocodePoints[0]->coordinates;

            $bounds = null;
            if (isset($item->bbox) && is_array($item->bbox) && count($item->bbox) > 0) {
                $bounds = array(
                    'south' => $item->bbox[0],
                    'west'  => $item->bbox[1],
                    'north' => $item->bbox[2],
                    'east'  => $item->bbox[3]
                );
            }

            $streetNumber = null;
            $streetName   = property_exists($item->address, 'addressLine') ? (string) $item->address->addressLine : '';
            $zipcode      = property_exists($item->address, 'postalCode') ? (string) $item->address->postalCode : '';
            $city         = property_exists($item->address, 'locality') ? (string) $item->address->locality: '';
            $county       = property_exists($item->address, 'adminDistrict2') ? (string) $item->address->adminDistrict2 : '';
            $region       = property_exists($item->address, 'adminDistrict') ? (string) $item->address->adminDistrict: '';
            $country      = property_exists($item->address, 'countryRegion') ? (string) $item->address->countryRegion: '';
            $neighborhood = property_exists($item->address, 'neighborhood') ? (string) $item->address->neighborhood: '';

            $results[] = array_merge($this->getDefaults(), array(
                'latitude'     => $coordinates[0],
                'longitude'    => $coordinates[1],
                'bounds'       => $bounds,
                'streetNumber' => $streetNumber,
                'streetName'   => $streetName,
                'city'         => empty($city) ? null : $city,
                'zipcode'      => empty($zipcode) ? null : $zipcode,
                'county'       => empty($county) ? null : $county,
                'region'       => empty($region) ? null : $region,
                'country'      => empty($country) ? null : $country,
                'neighborhood' => empty($neighborhood) ? null : $neighborhood
            ));
        }

        return $results;
    }
}
