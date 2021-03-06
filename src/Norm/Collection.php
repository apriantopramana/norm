<?php

namespace Norm;

use ROH\Util\Inflector;
use Norm\Model;
use Norm\Cursor;
use Norm\Filter\Filter;

class Collection extends Hookable implements \JsonKit\JsonSerializer
{
    public $clazz;
    public $name;
    public $connection;
    // public $schema;
    public $options;

    public $criteria;

    protected $filter;


    public function __construct(array $options = array())
    {
        $this->options = $options;

        $this->clazz = Inflector::classify($options['name']);
        $this->name = Inflector::tableize($this->clazz);
        $this->connection = $options['connection'];

        if (isset($options['observers'])) {
            foreach ($options['observers'] as $Observer => $options) {
                if (is_int($Observer)) {
                    $Observer = $options;
                    $options = null;
                }

                if (is_string($Observer)) {
                    $Observer = new $Observer($options);
                }
                $this->observe($Observer);
            }
        }
    }

    public function option($key)
    {
        return $this->options[$key] ?: null;
    }

    public function observe($observer)
    {
        if (method_exists($observer, 'saving')) {
            $this->hook('saving', array($observer, 'saving'));
        }

        if (method_exists($observer, 'saved')) {
            $this->hook('saved', array($observer, 'saved'));
        }

        if (method_exists($observer, 'removing')) {
            $this->hook('removing', array($observer, 'removing'));
        }

        if (method_exists($observer, 'removed')) {
            $this->hook('removed', array($observer, 'removed'));
        }

        if (method_exists($observer, 'searching')) {
            $this->hook('searching', array($observer, 'searching'));
        }

        if (method_exists($observer, 'searched')) {
            $this->hook('searched', array($observer, 'searched'));
        }

        if (method_exists($observer, 'attaching')) {
            $this->hook('attaching', array($observer, 'attaching'));
        }

        if (method_exists($observer, 'attached')) {
            $this->hook('attached', array($observer, 'attached'));
        }
    }

    public function schema($schema = null)
    {
        if (!isset($this->options['schema'])) {
            $this->options['schema'] = array();
        }

        if (func_num_args() === 0) {
            return $this->options['schema'];
        } elseif (is_array($schema)) {
            $this->options['schema'] = $schema;
        } elseif (empty($schema)) {
            $this->options['schema'] = array();
        } elseif (isset($this->options['schema'][$schema])) {
            return $this->options['schema'][$schema];
        }
    }

    public function prepare($key, $value, $schema = null)
    {
        if (is_null($schema)) {
            $schema = $this->schema($key);
            if (is_null($schema)) {
                return $value;
                // throw new \Exception('Cannot prepare data to set. Schema not found for key ['.$key.'].');
            }
        }
        return $schema->prepare($value);
    }

    public function hydrate($cursor)
    {
        $results = array();
        foreach ($cursor as $key => $doc) {
            $results[] = $this->attach($doc);
        }
        return $results;
    }

    public function attach($doc)
    {
        $doc = new \Norm\Type\Object($this->connection->prepare($this, $doc));
        $doc->clazz = $this->clazz;

        $this->applyHook('attaching', $doc);

        if (isset($this->options['model'])) {
            $Model = $this->options['model'];
            $model = new $Model($doc->toArray(), array(
                'collection' => $this,
            ));
        } else {
            $model = new Model($doc->toArray(), array(
                'collection' => $this,
            ));
        }

        $this->applyHook('attached', $model);

        return $model;
    }

    public function criteria($criteria = null)
    {
        if (isset($criteria)) {
            if (!isset($this->criteria)) {
                $this->criteria = array();
            }

            if (is_array($criteria)) {
                $this->criteria = $this->criteria + $criteria;
            } else {
                $this->criteria = array('$id' => $criteria);
            }
        }
    }

    public function find($criteria = null)
    {
        $this->criteria($criteria);

        $this->applyHook('searching', $this);

        $result = $this->connection->query($this);

        $this->applyHook('searched', $result);

        $cursor = new Cursor($result, $this);

        $this->criteria = null;

        return $cursor;
    }

    public function findOne($criteria = null)
    {
        $cursor = $this->find($criteria);
        $this->criteria = null;
        return $cursor->getNext();
    }

    // DEPRECATED reekoheek: moved to observer
    // public function rebuildTree($parent, $left) {
    //     // the right value of this node is the left value + 1
    //     $right = $left+1;

    //     // get all children of this node
    //     // $result = mysql_query('SELECT title FROM tree '.
    //     //                        'WHERE parent="'.$parent.'";');

    //     $result = $this->find(array('parent' => $parent));

    //     // while ($row = mysql_fetch_array($result)) {

    //     foreach ($result as $row) {
    //         // recursive execution of this function for each
    //         // child of this node
    //         // $right is the current right value, which is
    //         // incremented by the rebuild_tree function
    //         $right = $this->rebuildTree($row['$id'], $right);
    //     }

    //     // we've got the left value, and now that we've processed
    //     // the children of this node we also know the right value
    //     // mysql_query('UPDATE tree SET lft='.$left.', rgt='.
    //     //              $right.' WHERE title="'.$parent.'";');
    //     if (isset($parent)) {
    //         $model = $this->findOne($parent);
    //         $model['$lft'] = $left;
    //         $model['$rgt'] = $right;
    //         $model->save();
    //     }

    //     // return the right value of this node + 1
    //     return $right+1;
    // }

    // DEPRECATED reekoheek
    // public function findTree($parent, $criteria = null) {
    //     $this->criteria($criteria);

    //     if (empty($parent)) {
    //         $cursor = $this->connection->query($this)->sort(array('_lft' => 1));

    //         $right = array();
    //         $cache = array();

    //         $result = array();
    //         foreach ($cursor as $row) {
    //             if (count($right)>0) {
    //                 while (!empty($right[count($right)-1]) && $right[count($right)-1] < $row['_rgt']) {
    //                     array_pop($right);
    //                 }
    //             }

    //             $model = $this->attach($row);

    //             $cache[$row['_rgt']] = $model;

    //             if (count($right) > 0) {
    //                 $cache[$right[count($right)-1]]->add('children', $model);
    //             } else {
    //                 $result[$row['_rgt']] = &$cache[$row['_rgt']];
    //             }

    //             $right[] = $row['_rgt'];
    //         }

    //         return $result;

    //     } else {
    //         // FIXME reekoheek: unimplemented yet!
    //         // $this->find(array('$id' => $parent))

    //     }
    // }

    public function newInstance($cloned = array())
    {
        if ($cloned instanceof Model) {
            $cloned = $cloned->toArray(Model::FETCH_PUBLISHED);
        }
        if (isset($this->options['model'])) {
            $Model = $this->options['model'];
            return new $Model($cloned, array('collection' => $this));
        }
        return new Model($cloned, array('collection' => $this));
    }

    public function save(Model $model, $options = array())
    {
        if (!isset($options['filter']) || $options['filter'] === true) {
            $this->filter($model);
        }

        $this->applyHook('saving', $model, $options);

        $result = $this->connection->save($this, $model);

        $this->applyHook('saved', $model, $options);

        $this->criteria = null;

        return $result;
    }

    public function filter(Model $model, $key = null)
    {
        if (is_null($this->filter)) {
            $this->filter = Filter::fromSchema($this->schema());
        }

        return $this->filter->run($model, $key);
        // if (is_null($key)) {
        // } else {
        //     throw new \Exception(__METHOD__.' unimplemented selective field filter.');
        // }
    }

    public function remove($model)
    {

        $this->applyHook('removing', $model);

        $result = $this->connection->remove($this, $model);
        if ($result) {
            $model->reset();
        }

        $this->applyHook('removed', $model);

        $this->criteria = null;
        return $result;
    }

    public function migrate()
    {
        $this->connection->migrate($this);
    }

    public function jsonSerialize()
    {
        return $this->clazz;
    }
}
