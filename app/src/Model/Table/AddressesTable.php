<?php
declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Event\EventInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Addresses Model
 *
 * @method \App\Model\Entity\Address newEmptyEntity()
 * @method \App\Model\Entity\Address newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Address[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Address get($primaryKey, $options = [])
 * @method \App\Model\Entity\Address findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Address patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Address[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Address|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Address saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Address[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Address[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Address[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Address[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class AddressesTable extends Table
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

        $this->setTable('addresses');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
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
            ->maxLength('postal_code', 8, 'O campo postal_code só pode conter até 8 caractéres')
            ->requirePresence('postal_code', 'create', 'O campo postal_code é obrigatório')
            ->notEmptyString('postal_code', 'O campo postal_code é obrigatório', 'create')
            ->add('postal_code', 'custom', [
                'rule' => function ($value) {
                    if (!empty($value)) {
                        return $this->postalCodeCheck($value);
                    }
                    return false;
                },
                'message' => 'CEP não encontrado'
            ]);

        $validator
            ->maxLength('street_number', 200, 'O campo street_number só pode conter até 200 caractéres')
            ->requirePresence('street_number', 'create', 'O campo street_number é obrigatório')
            ->notEmptyString('street_number', 'O campo street_number é obrigatório', 'create');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param  RulesChecker  $rules  The rules object to be modified.
     * @return RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['foreign_table', 'foreign_id']), ['errorField' => 'foreign_table']);

        return $rules;
    }

    public function postalCodeCheck(string $postal_code): bool
    {
        $urlCepAberto = 'https://www.cepaberto.com/api/v3/cep?cep='.$postal_code;
        $urlViaCep = 'https://viacep.com.br/ws/'.$postal_code.'/json/';

        $address = $this->curlPostalCode($urlCepAberto, 'cep aberto');

        if(empty($address)) {
            if(empty($address = $this->curlPostalCode($urlViaCep, 'via cep'))) {
                return false;
            }
            return true;
        }
        return true;
    }

    public function curlPostalCode(string $url, string $service) : array
    {
        if ($service == 'cep aberto') {
            // consulta na API CEP Aberto
            $token = 'Token token=82c7a7b525c01e57986d56fde1945566';
            $headers = array(
                'Authorization:'.$token
            );
        } else {
            // consulta na API ViaCEP
            $headers = array();
        }

        $ch = curl_init($url);
        if(!$ch) {
            return array();
        } else {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($ch);
            curl_close($ch);
        }

        if ($result === true or $result === false) {
            return array();
        }

        return json_decode($result, true) ?: array();
    }
}
