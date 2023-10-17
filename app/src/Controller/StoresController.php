<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Store;
use App\Model\Table\StoresTable;
use Cake\Event\EventInterface;
use Cake\ORM\TableRegistry;
use Cake\Http\Response;
use Cake\View\JsonView;
use Cake\Datasource\Exception\RecordNotFoundException;

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

    public function index(): ?Response
    {
        $stores = $this->Stores->find()->all();
        $this->set('stores', $stores);
        $this->viewBuilder()->setOption('serialize', ['stores']);

        return null;
    }

    public function view(int $id): ?Response
    {
        try {
            $store = $this->Stores->get($id);
            $this->set('store', $store);
            $this->viewBuilder()->setOption('serialize', ['store']);
        } catch (RecordNotFoundException $e) {
            $message = 'Store not found';

            return $this->response
                ->withStatus(404)
                ->withStringBody(json_encode(['message' => $message]));
        }

        return null;
    }

    public function add(): ?Response
    {
        $this->request->allowMethod(['post']);
        $store = $this->Stores->newEmptyEntity();
        $this->save($store);

        return null;
    }

    public function edit(int $id): ?Response
    {
        $this->request->allowMethod(['put']);

        try {
            $store = $this->Stores->get($id);
            $this->save($store);
        } catch (RecordNotFoundException $e) {
            $message = 'Store not found';

            return $this->response
                ->withStatus(404)
                ->withStringBody(json_encode(['message' => $message]));
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
            $message = 'Store not found';

            return $this->response
                ->withStatus(404)
                ->withStringBody(json_encode(['message' => $message]));
        }

        return null;
    }

    /**
     * @param  Store  $store
     * @return void
     */
    public function save(Store $store): void
    {
        $store = $this->Stores->patchEntity($store, $this->request->getData());

        if ($this->Stores->save($store)) {
            $message = 'Saved';
        } else {
            $message = 'Error';
        }

        $this->set([
            'message' => $message,
            'store' => $store,
        ]);
        $this->viewBuilder()->setOption('serialize', ['store', 'message']);
    }
}
