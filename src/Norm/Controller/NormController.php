<?php

namespace Norm\Controller;

use \Bono\Controller\RestController;
use \Norm\Norm;

class NormController extends RestController {

    protected $collection;

    public function __construct($app, $uri) {
        parent::__construct($app, $uri);

        $this->collection = Norm::factory($this->clazz);
    }

    public function getCriteria() {
        $gets = $this->request->get();
        $criteria = array();
        foreach ($gets as $key => $value) {
            if ($key[0] !== '!') {
                $criteria[$key] = $value;
            }
        }
        return $criteria;
    }

    public function getSort() {
        $sorts = $get = $this->request->get('!sort') ? :array();
        foreach ($sorts as $key => &$value) {
            $value = (int) $value;
        }
        return $sorts;
    }

    public function search() {
        $entries = $this->collection->find($this->getCriteria())->sort($this->getSort());

        $this->data['entries'] = $entries;
    }

    public function create() {
        $entry = $this->getCriteria();

        if ($this->request->isPost()) {
            try {
                $entry = array_merge($entry, $this->request->post());
                $model = $this->collection->newInstance();
                $result = $model->set($entry)->save();

                $this->flash('info', $this->clazz.' created.');
                $this->redirect($this->getRedirectUri());
            } catch(\Exception $e) {
                $this->data['entry'] = $entry;
                $this->flashNow('error', ''.$e);
            }
        }

        $this->data['entry'] = $entry;
    }

    public function read($id) {
        $this->data['entry'] = $this->collection->findOne($id);

        if (is_null($this->data['entry'])) {
            $this->app->notFound();
        }
    }

    public function update($id) {
        $entry = $this->collection->findOne($id)->toArray();

        if ($this->request->isPost() || $this->request->isPut()) {
            try {
                $entry = array_merge($entry, $this->request->post());
                $model = $this->collection->findOne($id);
                $model->set($entry)->save();
                $this->flash('info', $this->clazz.' updated.');
                $this->redirect($this->getRedirectUri());
            } catch(\Exception $e) {
                $this->data['entry'] = $entry;
                $this->flashNow('error', ''.$e);
            }
        }
        $this->data['entry'] = $entry;
    }

    public function delete($id) {
        if ($this->request->isPost() || $this->request->isDelete()) {
            $model = $this->collection->findOne($id);
            $model->remove();

            $this->flash('info', $this->clazz.' deleted.');
            $this->redirect($this->getRedirectUri());
        }
    }

    public function getRedirectUri() {
        $continue = $this->request->get('@continue');
        if (empty($continue)) {
            return $this->getBaseUri();
        } else {
            return $continue;
        }
    }
}
