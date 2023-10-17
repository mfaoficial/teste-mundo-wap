<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Table\StoresTable;
use Cake\Event\EventInterface;
use Cake\ORM\TableRegistry;
use Cake\Http\Response;
use Cake\View\JsonView;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Validation\Validator;

class StoresController extends AppController
{
    private StoresTable $Stores;

    public function initialize(): void
    {
        parent::initialize();

        /**
         * @var StoresTable $Stores
         */
        $Stores = TableRegistry::getTableLocator()->get('Stores');
        $this->Stores = $Stores;
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

        try {
            $store = json_encode($this->Stores->get($id)) ?: 'Ocorreu um erro inesperado, tente novamente mais tarde.';
        } catch (RecordNotFoundException $e) {
            $message = 'Registro não encontrado';

            return $this->response
                ->withStatus(404)
                ->withStringBody($message);
        }

        return $this->response
            ->withStatus(200)
            ->withStringBody($store);
    }

    public function add(): Response
    {
        $this->request->allowMethod(['post']);

        $store = $this->Stores->newEmptyEntity();
        $store = $this->Stores->patchEntity($store, $this->request->getData());

        if (!($this->Stores->save($store))) {
            $message = json_encode($store->getErrors()) ?: 'Ocorreu um erro inesperado, tente novamente mais tarde.';

            return $this->response
                ->withStatus($message == 'Ocorreu um erro inesperado, tente novamente mais tarde.' ? 404 : 400)
                ->withStringBody($message);
        }

        $message = 'Registro criado com sucesso';

        return $this->response
            ->withStatus(201)
            ->withStringBody($message);
    }

    public function edit(int $id): ?Response
    {
        $this->request->allowMethod(['put']);

        try {
            $store = $this->Stores->get($id);
            $this->save($store);
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
}
