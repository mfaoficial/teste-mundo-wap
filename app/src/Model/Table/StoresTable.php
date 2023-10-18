<?php
declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
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
                'message' => 'Nome em uso'
            ]);

        return $validator;
    }

    /**
     * @throws Exception
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): bool
    {
        $addressesTable = TableRegistry::getTableLocator()->get('Addresses');
        $address = $addressesTable->newEmptyEntity();

        if (!empty($options['update'])) {
            $address = $addressesTable->find('all', [
                'conditions' => [
                    'foreign_table' => 'stores',
                    'foreign_id' => $entity->id
                ]
            ])->firstOrFail();
        }

        if (!($address instanceof \Cake\Datasource\EntityInterface)) {
            throw new Exception('Ocorreu um erro inesperado, tente novamente mais tarde.');
        }

        $urlCepAberto = 'https://www.cepaberto.com/api/v3/cep?cep='.$options['address']['postal_code'];
        $urlViaCep = 'https://viacep.com.br/ws/'.$options['address']['postal_code'].'/json/';

        $completeAddress = (new AddressesTable())->curlPostalCode($urlCepAberto, 'cep aberto');

        if (!empty($completeAddress['message']) or empty($completeAddress)) {
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

                $address = $addressesTable->patchEntity($address, $options['address']);
                if ($addressesTable->save($address)) {
                    return true;
                } else {
                    throw new Exception('Ocorreu um erro inesperado, tente novamente mais tarde.');
                }
            }
        }

        $options['address']['foreign_table'] = 'stores';
        $options['address']['foreign_id'] = $entity->id;
        $options['address']['neighborhood'] = $completeAddress['bairro'];
        $options['address']['city'] = $completeAddress['cidade']['nome'];
        $options['address']['state'] = $completeAddress['estado']['sigla'];
        $options['address']['sublocality'] = $completeAddress['cidade']['ibge'];
        $options['address']['street'] = $completeAddress['logradouro'];

        $address = $addressesTable->patchEntity($address, $options['address']);
        if ($addressesTable->save($address)) {
            return true;
        }
        throw new Exception('Ocorreu um erro inesperado, tente novamente mais tarde.');
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

        try {
            $addressesTable->delete($address);
            return true;
        } catch (Exception $exception) {
            throw new Exception('Ocorreu um erro inesperado, tente novamente mais tarde.');
        }
    }
}
