<?php
/**
 * Created by PhpStorm.
 * User: nts
 * Date: 31.3.18.
 * Time: 17.00
 */

namespace PrestaShop\Builders;

use JsonSerializable;
use PrestaShop\Classes\PrestaShopWebserviceException;
use PrestaShop\Traits\Filtering;
use PrestaShop\Traits\Limiting;
use PrestaShop\Traits\Parser;
use PrestaShop\Utils\Model;
use PrestaShop\Utils\Request;
use SimpleXMLElement;


class Builder
{
    use Filtering,
        Limiting,
        Parser;

    protected $entity;
    protected $detailsEntity;
    /** @var Model */
    protected $model;
    private   $request;

    public function __construct(Request $request )
    {
        $this->request = $request;
    }

    /**
     * Get list of all entries, including details if you wish
     *
     * @param array $filters
     * @param bool $details
     *
     * @return mixed
     * @throws PrestaShopWebserviceException
     */
    public function get( array $filters = [], $limit = null, bool $details = false, string $url = null )
    {

        $optFilters = $this->prepareFilters( $filters );
        $optLimits  = $this->prepareLimit( $limit );

        return $this->request->handleWithExceptions( function () use ( $optFilters, $details, $optLimits, $filters, $url
        ) {

            if ( !is_null( $url ) ) {

                if ( !strpos( $url, 'output_format' ) ) {

                    if ( !strpos( $url, '?' ) ) {

                        $url .= '?output_format=JSON';

                    } else {

                        $url .= '&output_format=JSON';
                    }
                }

                $opt = [

                    'url'           => $url,
                    'output_format' => 'JSON',
                ];

            } else {

                $opt = [

                    'resource'      => $this->entity,
                    'output_format' => 'JSON',
                ];

                if ( count( $optFilters ) ) {

                    $opt = array_merge( $opt, $optFilters );

                    if (array_key_exists('date_add', $filters)) {

                        $opt['date'] = 1;
                    }
                }

                if ( count( $optLimits ) ) {

                    $opt = array_merge( $opt, $optLimits );
                }
            }

            $response = $this->request->client->get( $opt );

            $fetchedItems = collect( $response );

            $items = collect( [] );

            if ( $fetchedItems->count() ) {
                foreach ($fetchedItems->first() as $index => $item ) {

                    $identifier = $item[ $this->primaryKey ] ?? null;

                    if ( $identifier ) {

                        $item = [

                            $this->primaryKey => $identifier,
                        ];

                        /** @var Model $model */
                        $model = new $this->model( $this->request, $item );

                        $items->push( $model );
                    }


                }

                if ( $details ) {


                    foreach ($items as $index => $item ) {

                        $item = $this->find( $item->{$this->primaryKey} );

                        $items[ $index ] = $item;
                    }
                }
            }


            return $items;
        } );
    }

    /**
     * Find specific resource based on primary key/identifier
     *
     * @param $id
     *
     * @return mixed
     * @throws PrestaShopWebserviceException
     */
    public function find( $id )
    {
        return $this->request->handleWithExceptions( function () use ( $id ) {

            $response = $this->request->client->get( [

                'resource'        => $this->detailsEntity,
                'output_format'   => 'JSON',
                $this->primaryKey => $id,
            ] );

            //dd( $response->children()->children() );
            $responseData = collect( $response );

            return new $this->model( $this->request, $responseData->first() );
        } );
    }

    /**
     * Create new resource
     *
     * @param $data
     *
     * @return mixed
     * @throws PrestaShopWebserviceException
     */
    public function create( $data )
    {

        return $this->request->handleWithExceptions( function () use ( $data ) {

            $doc = new SimpleXMLElement( '<prestashop/>' );

            $this->arrayToXml( $doc, $data );

            $xml = $doc->asXML();

            $response = $this->request->client->add( [

                'resource' => $this->entity,
                'postXml'  => $xml,
            ] );

            $data = $this->xmlToArray( $response->children()->children() );


            return new $this->model($this->request, $data);
        });
    }


    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @param $new_entity
     * @return $this
     */
    public function setEntity($new_entity)
    {
        $this->entity = $new_entity;

        return $this;
    }

    /**
     * @param $entity
     * @return $this
     */
    public function setDetailsEntity($entity)
    {
        $this->detailsEntity = $entity;

        return $this;
    }

    public function getDetailEntity()
    {
        return $this->detailsEntity;
    }

    /**
     * @param $url
     *
     * @return \PrestaShop\Classes\SimpleXMLElement|JsonSerializable
     * @throws PrestaShopWebserviceException
     */
    public function blank($url)
    {
        return $this->request->client->get([

            'url'           => $url . '/api/' . $this->entity . '?schema=blank&output_format=JSON',
            'output_format' => 'JSON',
        ] );
    }

    private function switchComparison( $comparison )
    {
        switch ( $comparison ) {
            case '=':
            case '==':
                $newComparison = '$eq:';
                break;
            case '!=':
                $newComparison = '$ne:';
                break;
            case '>':
                $newComparison = '$gt:';
                break;
            case '>=':
                $newComparison = '$gte:';
                break;
            case '<':
                $newComparison = '$lt:';
                break;
            case '<=':
                $newComparison = '$lte:';
                break;
            case 'like':
                $newComparison = '$like:';
                break;
            case 'in':
                $newComparison = '$in:';
                break;
            case '!in':
                $newComparison = '$nin:';
                break;
            default:
                $newComparison = "${$comparison}:";
                break;
        }

        return $newComparison;
    }

    private function escapeFilter( $variable )
    {
        $escapedStrings    = [
            "$",
            '(',
            ')',
            '*',
            '[',
            ']',
            ',',
        ];
        $urlencodedStrings = [
            '+',
            ' ',
        ];
        foreach ($escapedStrings as $escapedString ) {

            $variable = str_replace( $escapedString, '$' . $escapedString, $variable );
        }
        foreach ($urlencodedStrings as $urlencodedString ) {

            $variable = str_replace( $urlencodedString, urlencode( $urlencodedString ), $variable );
        }

        return $variable;
    }
}