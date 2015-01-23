<?php

namespace Geocoder\Provider;

use Geocoder\Exception\InvalidCredentialsException;
use Geocoder\HttpAdapter\HttpAdapterInterface;
use Geocoder\Exception\NoResultException;
use Geocoder\Exception\UnsupportedException;

/**
 * @author Andreas Kristiansen <mail@ankr.dk>
 */
class MapBoxProvider extends AbstractProvider implements ProviderInterface
{
	/**
	 * @const string
	 */
	const GEOCODE_ENDPOINT_URL = 'http://api.tiles.mapbox.com/v4/geocode/mapbox.places/%s.json?access_token=%s';

	/**
	 * API Access Token
	 *
	 * @var string
	 */
	protected $apiKey = null;

	/**
	 * Constructor
	 *
	 * @param HttpAdapterInterface $adapter An HTTP adapter.
	 * @param string $apiKey An API key.
	 * @param string|null $locale A locale (optional).
	 * @return void
	 */
	public function __construct(HttpAdapterInterface $adapter, $apiKey, $locale = null)
	{
		parent::__construct($adapter, $locale);
		$this->apiKey = $apiKey;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getGeocodedData($address)
	{
		if (!$this->apiKey) {
			throw new InvalidCredentialsException('No API Key provided.');
		}

		$query = sprintf(self::GEOCODE_ENDPOINT_URL, urlencode($address), $this->apiKey);

		return $this->executeQuery($query);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReversedData(array $coordinates)
	{
		throw new \BadMethodCallException('MapBoxProvider does not yet support reversed lookups');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName()
	{
		return 'map_box';
	}

	/**
	 * Query a constructed end point
	 *
	 * @param string $query
	 * @return array
	 */
	protected function executeQuery($query)
	{
		$content = $this->getAdapter()->getContent($query);

		if (!$content) {
			throw new NoResultException(sprintf('Could not execute query: %s', $query));
		}

		$json = json_decode($content, true);

		if (empty($json['features'])) {
			throw new NoResultException(sprintf('Could not find results for given query: %s', $query));
		}

		$results = [];

		foreach ($json['features'] as $location) {
			if ($location['relevance'] < .75) {
				continue;
			}

			$results[] = array_merge($this->getDefaults(), [
				'latitude' => $location['center'][1],
				'longitude' => $location['center'][0],
				'city' => $location['text']
			]);
		}

		if (empty($results)) {
			throw new NoResultException(sprintf('Could not find results for given query: %s', $query));
		}

		return $results;
	}

}
