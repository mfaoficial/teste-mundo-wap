<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Address Entity
 *
 * @property int $id
 * @property string $foreign_table
 * @property int $foreign_id
 * @property string $postal_code
 * @property string $state
 * @property string $city
 * @property string $sublocality
 * @property string $street
 * @property string $street_number
 * @property string $complement
 */
class Address extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'id' => false,
        'foreign_table' => false,
        'foreign_id' => false,
        'postal_code' => true,
        'state' => false,
        'city' => false,
        'sublocality' => false,
        'street' => false,
        'street_number' => true,
        'complement' => true,
    ];
}
