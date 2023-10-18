<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\AddressesTable;
use App\Model\Table\StoresTable;
use Cake\Database\Exception\NestedTransactionRollbackException;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\ORM\TableRegistry;
use Cake\Http\Response;
use Cake\View\JsonView;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Validation\Validator;
use PHPStan\Type\MixedType;

class StoresController extends AppController
{
    private StoresTable $Stores;
    private AddressesTable $Addresses;

    public function initialize(): void
    {
        parent::initialize();

        /**
         * @var StoresTable $Stores
         */
        $Stores = TableRegistry::getTableLocator()->get('Stores');
        $this->Stores = $Stores;

        /**
         * @var AddressesTable $Addresses
         */
        $Addresses = TableRegistry::getTableLocator()->get('Addresses');
        $this->Addresses = $Addresses;
    }

    public function viewClasses(): array
    {
        return [JsonView::class];
    }

    /* Garante que sempre retorna JSON independente do que vem em Accept no Header, utilizei isto devido a retornar
     * $this->response fazer retornar tudo branco no postman.
    */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->viewBuilder()->setClassName('Json');
    }


    public function index(): Response
    {
        $this->request->allowMethod(['get']);

        $stores = json_encode($this->Stores->find()->all()) ?: 'Ocorreu um erro inesperado, tente novamente mais tarde.';

        return $this->response
            ->withStatus($stores == 'Ocorreu um erro inesperado, tente novamente mais tarde.' ? 404 : 200)
            ->withStringBody($stores);
    }

    public function view(int $id): Response
    {
        $this->request->allowMethod(['get']);

        $store = json_encode($this->Stores->get($id)) ?: 'Registro não encontrado';

        return $this->response
            ->withStatus($store == 'Registro não encontrado' ? 404 : 200)
            ->withStringBody($store);
    }

    public function add(): Response
    {
        $this->request->allowMethod(['post']);

        $store = $this->Stores->newEmptyEntity();
        $store = $this->Stores->patchEntity($store, $this->request->getData());

        $validatedAddress = $this->validateAddress($this->request->getData());

        if (!empty($validatedAddress)) {
            return $this->response
                ->withStatus(400)
                ->withStringBody(json_encode($validatedAddress) ?: 'Ocorreu um erro inesperado, tente novamente mais tarde.');
        }

        $address = [
            'postal_code' => $this->request->getData('postal_code'),
            'street_number' => $this->request->getData('street_number'),
            'complement' => $this->request->getData('complement'),
        ];

        $connection = ConnectionManager::get('default');
        $connection->begin();

        try {
            if (!($this->Stores->save($store, ['address' => $address]))) {
                if (!empty($store->getErrors())) {
                    $message = json_encode($store->getErrors()) ?: 'Ocorreu um erro inesperado, tente novamente mais tarde.';
                } else {
                    $message = 'Ocorreu um erro inesperado, tente novamente mais tarde.';
                }

                $connection->rollback();
                return $this->response
                    ->withStatus($message == 'Ocorreu um erro inesperado, tente novamente mais tarde.' ? 404 : 400)
                    ->withStringBody($message);
            }
        } catch (\Exception $exception) {
            $message = 'Ocorreu um erro inesperado, tente novamente mais tarde.';

            $connection->rollback();
            return $this->response
                ->withStatus(400)
                ->withStringBody($message);
        }


        $connection->commit();
        $message = 'Registro criado com sucesso';

        return $this->response
            ->withStatus(201)
            ->withStringBody($message);
    }

    public function edit(int $id): Response
    {
        $this->request->allowMethod(['put']);

        try {
            $store = $this->Stores->get($id);
            $store = $this->Stores->patchEntity($store, $this->request->getData());

            if (!empty($this->request->getData('postal_code')) or !empty($this->request->getData('street_number'))) {
                $validatedAddress = $this->validateAddress($this->request->getData());

                if (!empty($validatedAddress)) {
                    return $this->response
                        ->withStatus(400)
                        ->withStringBody(json_encode($validatedAddress) ?: 'Ocorreu um erro inesperado, tente novamente mais tarde.');
                }

                $address = [
                    'postal_code' => $this->request->getData('postal_code') ?? $store->postal_code,
                    'street_number' => $this->request->getData('street_number') ?? $store->street_number,
                    'complement' => $this->request->getData('complement') ?? $store->complement,
                ];

                $connection = ConnectionManager::get('default');
                $connection->begin();

                if (!($this->Stores->save($store, ['address' => $address, 'update' => true]))) {
                    $connection->rollback();
                    $message = json_encode($store->getErrors()) ?: 'Ocorreu um erro inesperado, tente novamente mais tarde.';

                    return $this->response
                        ->withStatus($message == 'Ocorreu um erro inesperado, tente novamente mais tarde.' ? 404 : 400)
                        ->withStringBody($message);
                }
            }

            $message = 'Registro atualizado com sucesso';

            return $this->response
                ->withStatus(200)
                ->withStringBody($message);

        } catch (RecordNotFoundException $e) {
            $message = 'Registro não encontrado';

            return $this->response
                ->withStatus(404)
                ->withStringBody($message);
        }
    }

    public function delete(int $id): ?Response
    {
        $this->request->allowMethod(['delete']);

        try {
            $store = $this->Stores->get($id);

            if ($this->Stores->delete($store)) {
                $message = 'Deleted';
            } else {
                $message = 'Error';
            }

            $this->set('message', $message);
            $this->viewBuilder()->setOption('serialize', ['message']);
        } catch (RecordNotFoundException $e) {
            $message = json_encode(['message' => 'Store not found']);

            if ($message === false) {
                $message = 'An error occurred while encoding the error message.';
            }

            return $this->response
                ->withStatus(404)
                ->withStringBody($message);
        }

        return null;
    }

    private function validateAddress(array $data): array
    {
        $validator = new Validator();
        $validator
            ->maxLength('postal_code', 8, 'O campo postal_code só pode conter até 8 caractéres')
            ->requirePresence('postal_code', true, 'O campo postal_code é obrigatório')
            ->notEmptyString('postal_code', 'O campo postal_code é obrigatório')
            ->add('postal_code', 'custom', [
                'rule' => function ($value) {
                    return $this->Addresses->postalCodeCheck($value);
                },
                'message' => 'CEP não encontrado'
            ]);

        $validator
            ->maxLength('street_number', 200, 'O campo street_number só pode conter até 200 caractéres')
            ->requirePresence('street_number', true, 'O campo street_number é obrigatório')
            ->notEmptyString('street_number', 'O campo street_number é obrigatório');

        $addressValidation = $validator->validate($data);

        if (!empty($addressValidation)) {
            return $addressValidation;
        }

        return array();
    }
}
