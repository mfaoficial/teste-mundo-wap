<?php
declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\TableRegistry;
use Exception;

/**
 * Stores Model
 *
 * @method \App\Model\Entity\Store newEmptyEntity()
 * @method \App\Model\Entity\Store newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Store[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Store get($primaryKey, $options = [])
 * @method \App\Model\Entity\Store findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Store patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Store[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Store|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Store saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Store[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Store[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Store[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Store[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class StoresTable extends Table
{
    /**
     * Initialize method
     *
     * @param  array  $config  The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('stores');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->hasOne('Addresses', [
            'foreignKey' => 'foreign_id',
            'conditions' => [
                'Addresses.foreign_table' => 'stores'
            ]
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param  Validator  $validator  Validator instance.
     * @return Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->maxLength('name', 200, 'O campo name só pode conter até 200 caractéres')
            ->requirePresence('name', true, 'O campo name é obrigatório')
            ->notEmptyString('name', 'O campo name é obrigatório')
            ->add('name', 'unique', [
                'rule' => 'validateUnique',
                'provider' => 'table',
                'message' => 'Nome em uso',
                'exclude' => ['id'],
            ]);

        return $validator;
    }

    /**
     * @throws Exception
     */
    public function updateOnlyAddress(EntityInterface $entity, ArrayObject $options): bool
    {
        /** @var Connection $connection */
        $connection = ConnectionManager::get('default');

        if (!empty($entity->getErrors())) {
            throw new Exception('Ocorreu um erro inesperado, tente novamente mais tarde.');
        }

        $addressesTable = TableRegistry::getTableLocator()->get('Addresses');
        $address = $addressesTable->find('all', [
            'conditions' => [
                'foreign_table' => 'stores',
                'foreign_id' => $entity->id
            ]
        ])->firstOrFail();

        // If the postal code is different from the previous one, update the address
        if ($address->get('postal_code') !== $options['address']['postal_code'] or $address->get('street_number') !== $options['address']['street_number']) {
            return $this->checkPostalCodeTwice($options, $entity, $addressesTable, $address, $connection);
        } else {
            return false;
        }
    }

    /**
     * @throws Exception
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): bool
    {
        $addressesTable = TableRegistry::getTableLocator()->get('Addresses');
        $address = $addressesTable->newEmptyEntity();

        /** @var Connection $connection */
        $connection = ConnectionManager::get('default');

        if (!empty($options['update'])) {
            $address = $addressesTable->find('all', [
                'conditions' => [
                    'foreign_table' => 'stores',
                    'foreign_id' => $entity->id
                ]
            ])->firstOrFail();
            $address = $addressesTable->newEmptyEntity();
        }

        return $this->checkPostalCodeTwice($options, $entity, $addressesTable, $address, $connection);
    }

    /**
     * @throws Exception
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options): bool
    {
        $addressesTable = TableRegistry::getTableLocator()->get('Addresses');
        $address = $addressesTable->find('all', [
            'conditions' => [
                'foreign_table' => 'stores',
                'foreign_id' => $entity->id
            ]
        ])->first();

        if (!($address instanceof \Cake\Datasource\EntityInterface)) {
            throw new Exception('Ocorreu um erro inesperado, tente novamente mais tarde.');
        }

        try {
            $addressesTable->delete($address);
            return true;
        } catch (Exception $exception) {
            throw new Exception('Ocorreu um erro inesperado, tente novamente mais tarde.');
        }
    }

    /**
     * @param  Table  $addressesTable
     * @param  EntityInterface  $address
     * @param $newAddress
     * @param  Connection  $connection
     * @return true
     * @throws Exception
     */
    public function saveNewAddress(Table $addressesTable, EntityInterface $address, $newAddress, Connection $connection): bool
    {
        // Delete the old register to create a new one
        $connection = ConnectionManager::get('default');
        $addressesTable->delete($address);
        $connection->commit();

        $address = $addressesTable->newEmptyEntity();
        $address = $addressesTable->patchEntity($address, $newAddress);
        if ($addressesTable->save($address)) {
            $connection->commit();
            return true;
        } else {
            throw new Exception('Ocorreu um erro inesperado, tente novamente mais tarde.');
        }
    }

    /**
     * @param  string  $urlViaCep
     * @param  ArrayObject  $options
     * @param  EntityInterface  $entity
     * @param  array  $completeAddress
     * @param  Table  $addressesTable
     * @param  EntityInterface  $address
     * @param  Connection  $connection
     * @return bool
     * @throws Exception
     */
    public function fillCepAbertoData(
        string $urlViaCep,
        ArrayObject $options,
        EntityInterface $entity,
        array $completeAddress,
        Table $addressesTable,
        EntityInterface $address,
        Connection $connection
    ): bool {
        if ((new AddressesTable())->curlPostalCode($urlViaCep,
                'via cep')['erro'] === true or empty((new AddressesTable())->curlPostalCode($urlViaCep,
                'via cep'))) {
            throw new Exception('CEP não encontrado');
        } else {
            $options['address']['foreign_table'] = 'stores';
            $options['address']['foreign_id'] = $entity->id;
            $options['address']['neighborhood'] = $completeAddress['bairro'];
            $options['address']['city'] = $completeAddress['localidade'];
            $options['address']['state'] = $completeAddress['uf'];
            $options['address']['sublocality'] = $completeAddress['ibge'];
            $options['address']['street'] = $completeAddress['logradouro'];

            return $this->saveNewAddress($addressesTable, $address, $options['address'], $connection);
        }
    }

    /**
     * @param  ArrayObject  $options
     * @param  EntityInterface  $entity
     * @param  array  $completeAddress
     * @return array|ArrayObject
     */
    public function fillViaCepData(ArrayObject $options, EntityInterface $entity, array $completeAddress)
    {
        $options['address']['foreign_table'] = 'stores';
        $options['address']['foreign_id'] = $entity->id;
        $options['address']['neighborhood'] = $completeAddress['bairro'];
        $options['address']['city'] = $completeAddress['cidade']['nome'];
        $options['address']['state'] = $completeAddress['estado']['sigla'];
        $options['address']['sublocality'] = $completeAddress['cidade']['ibge'];
        $options['address']['street'] = $completeAddress['logradouro'];
        return $options;
    }

    /**
     * @param  ArrayObject  $options
     * @param  EntityInterface  $entity
     * @param  Table  $addressesTable
     * @param  EntityInterface  $address
     * @param  Connection  $connection
     * @return bool
     * @throws Exception
     */
    public function checkPostalCodeTwice(
        ArrayObject $options,
        EntityInterface $entity,
        Table $addressesTable,
        EntityInterface $address,
        Connection $connection
    ): bool {
        $urlCepAberto = 'https://www.cepaberto.com/api/v3/cep?cep='.$options['address']['postal_code'];
        $urlViaCep = 'https://viacep.com.br/ws/'.$options['address']['postal_code'].'/json/';

        $completeAddress = (new AddressesTable())->curlPostalCode($urlCepAberto, 'cep aberto');

        if (!empty($completeAddress['message']) or empty($completeAddress)) {
            return $this->fillCepAbertoData($urlViaCep, $options, $entity, $completeAddress, $addressesTable, $address,
                $connection);
        } else {
            $options = $this->fillViaCepData($options, $entity, $completeAddress);

            return $this->saveNewAddress($addressesTable, $address, $options['address'], $connection);
        }
    }
}
