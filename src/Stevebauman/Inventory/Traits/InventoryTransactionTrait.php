<?php

namespace Stevebauman\Inventory\Traits;

use Stevebauman\Inventory\Exceptions\StockNotFoundException;
use Stevebauman\Inventory\Exceptions\InvalidTransactionStateException;
use Stevebauman\Inventory\Models\InventoryTransaction;

/**
 * Class InventoryTransactionTrait
 * @package Stevebauman\Inventory\Traits
 */
trait InventoryTransactionTrait
{
    use DatabaseTransactionTrait;

    /**
     * Stores the state before an update
     *
     * @var string
     */
    protected $beforeState = '';

    /**
     * Stores the quantity before an update
     *
     * @var string
     */
    protected $beforeQuantity = 0;

    /**
     * Overrides the models boot function to generate a new transaction history
     * record when it is created and updated
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        parent::creating(function($model)
        {
            if(!$model->beforeState) $model->beforeState = $model::STATE_OPENED;
        });

        parent::created(function($model)
        {
            $model->postCreate();
        });

        parent::updating(function($model)
        {
            /*
             * Retrieve the original quantity before it was updated,
             * so we can create generate an update with it
             */
            $model->beforeState = $model->getOriginal('state');
            $model->beforeQuantity = $model->getOriginal('quantity');
        });

        parent::updated(function($model)
        {
            $model->postUpdate();
        });
    }

    /**
     * Generates a transaction history record after a transaction has been created
     *
     * @return void
     */
    public function postCreate()
    {
        /**
         * Make sure the transaction does not already have a history record
         * before creating one
         */
        if(!$this->getLastHistoryRecord())
        {
            $this->generateTransactionHistory($this->beforeState, $this->state, 0, $this->quantity);
        }
    }

    /**
     * Generates a transaction history record when a transaction has been updated
     *
     * @return void
     */
    public function postUpdate()
    {
        $this->generateTransactionHistory($this->beforeState, $this->state, $this->beforeQuantity, $this->quantity);
    }

    /**
     * The belongsTo stock relationship
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    abstract public function stock();

    /**
     * The hasMany histories relationship
     *
     * @return mixed
     */
    abstract public function histories();

    /**
     * Checks out the specified amount of quantity from the stock,
     * waiting to be sold.
     *
     * @param $quantity
     * @return mixed
     * @throws \Stevebauman\Inventory\Exceptions\NotEnoughStockException
     * @throws \Stevebauman\Inventory\Exceptions\InvalidQuantityException
     * @throws StockNotFoundException
     */
    public function checkout($quantity)
    {
        $stock = $this->getStockRecord();

        $stock->isValidQuantity($quantity);

        $stock->hasEnoughStock($quantity);

        $this->quantity = $quantity;

        $this->state = $this::STATE_COMMERCE_CHECKOUT;

        $this->dbStartTransaction();

        try
        {
            if($stock->take($quantity, 'Stock transaction: Checked out') && $this->save())
            {
                $this->dbCommitTransaction();

                $this->fireEvent('inventory.transaction.checkout', array(
                    'transaction' => $this,
                ));

                return $this;
            }
        } catch(\Exception $e)
        {
            $this->dbRollbackTransaction();
        }

        return false;
    }

    /**
     * Marks and removes the specified amount of quantity sold from the stock. If no quantity is specified
     * and the previous state was not in checkout, this will throw an exception
     *
     * @param null $quantity
     * @return mixed
     * @throws \Stevebauman\Inventory\Exceptions\NotEnoughStockException
     * @throws \Stevebauman\Inventory\Exceptions\InvalidQuantityException
     * @throws StockNotFoundException
     */
    public function sold($quantity = NULL)
    {
        if($quantity)
        {
            $stock = $this->getStockRecord();

            $stock->take($quantity);

            $this->quantity = $quantity;

            $this->state = InventoryTransaction::STATE_COMMERCE_SOLD;

            $this->dbStartTransaction();

            try
            {
                if($this->save())
                {
                    $this->dbCommitTransaction();

                    $this->fireEvent('inventory.transaction.sold', array(
                        'transaction' => $this,
                    ));

                    return $this;
                }
            } catch(\Exception $e)
            {
                $this->dbRollbackTransaction();
            }

            return false;
        } else
        {
            $movement = $this->getLastHistoryRecord();


        }
    }

    /**
     * Returns the specified amount of quantity back into the stock. A previous
     * state is required to successfully insert the quantity back into the stock, for
     * example, if the stock was sold, or was in checkout, the returned method could
     * be called and the quantity that was sold or was in checkout would be inserted
     * back into the stock. If a quantity is specified and it is less than the amount
     * that was sold/checked-out, then the specified amount is inserted back into the stock
     * and the transaction is reverted to its previous state with the leftover amount.
     *
     * @param null $quantity
     * @return mixed
     */
    public function returned($quantity = NULL)
    {

    }

    /**
     * Reserves the specified amount of quantity for a reservation for commerce.
     * If backOrder is true then the state will be set to back-order if the specified
     * quantity is unavailable to be reserved. Otherwise it will throw an exception
     *
     * @param $quantity
     * @param bool $backOrder
     * @return mixed
     */
    public function reserved($quantity, $backOrder = false)
    {

    }

    /**
     * Back-orders the specified amount of quantity on the stock, if stock is sufficient enough
     * for the quantity specified, this will throw an exception. This prevents back-orders
     * being created when unnecessary
     *
     * @param $quantity
     * @return mixed
     */
    public function backOrder($quantity)
    {

    }

    /**
     * Creates a transaction that specifies the amount of quantity that has been ordered.
     * The received or cancel method must be used after this is performed.
     *
     * @param $quantity
     * @return mixed
     */
    public function ordered($quantity)
    {

    }

    /**
     * Marks a transaction as received. If the previous state was ordered then the amount
     * ordered is inserted into the stock. If a quantity is specified then the status of the
     * transaction is set to received-partial, and then returned to ordered with the amount of
     * quantity left to receive
     *
     * @param null $quantity
     * @return mixed
     */
    public function received($quantity = NULL)
    {

    }

    /**
     * Holds the specified amount of quantity from the inventory stock. This will remove
     * the quantity from the stock and hold it inside the transaction until further action is taken.
     *
     * @param $quantity
     * @return mixed
     */
    public function hold($quantity)
    {

    }

    /**
     * Releases held inventory and inserts it back into the stock. If a quantity is specified
     * and it is lower than the held quantity, than the transaction state will change to
     * released-partial and then returned to the state on-hold with the remainder of the
     * held stock
     *
     * @param $quantity
     * @return mixed
     */
    public function release($quantity = NULL)
    {

    }

    /**
     * Cancels any transaction and returns or removes stock depending on the last state
     *
     * @return mixed
     */
    public function cancel()
    {

    }

    /**
     * Returns true/false depending if the current
     * transaction is attached to a stock
     *
     * @return bool
     */
    public function hasStock()
    {
        if($this->stock) return true;

        return false;
    }


    /**
     * Returns the current stock record attached to the current
     * transaction
     *
     * @return mixed
     * @throws StockNotFoundException
     */
    public function getStockRecord()
    {
        if($this->hasStock()) return $this->stock;

        $message = "Transaction is not associated with a stock";

        throw new StockNotFoundException($message);
    }

    /**
     * Returns the current transaction history
     *
     * @return mixed
     */
    public function getHistory()
    {
        return $this->histories;
    }

    /**
     *  Returns the last transaction history record
     *
     * @return bool|mixed
     */
    public function getLastHistoryRecord()
    {
        $record = $this->histories()->orderBy('created_at', 'DESC')->first();

        if($record) return $record;

        return false;
    }

    /**
     * Verifies if the state being set is valid
     *
     * @param $state
     * @throws InvalidTransactionStateException
     */
    public function setStateAttribute($state)
    {
        $this->validateState($state);

        $this->attributes['state'] = $state;
    }

    /**
     * Returns true if the specified state is valid, throws an
     * exception otherwise
     *
     * @param $state
     * @return bool
     * @throws InvalidTransactionStateException
     */
    private function validateState($state)
    {
        if(!in_array($state, $this->getAvailableStates()))
        {
            $message = "State: $state is an invalid state.";

            throw new InvalidTransactionStateException($message);
        }

        return true;
    }

    /**
     * Processes generating a transaction history entry
     *
     * @param $stateBefore
     * @param $stateAfter
     * @param int $quantityBefore
     * @param int $quantityAfter
     * @return mixed
     */
    private function generateTransactionHistory($stateBefore, $stateAfter, $quantityBefore = 0, $quantityAfter = 0)
    {
        $insert = array(
            'transaction_id' => $this->id,
            'state_before' => $stateBefore,
            'state_after' => $stateAfter,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
        );

        return $this->histories()->create($insert);
    }

    /**
     * Returns an array of available states
     *
     * @return array
     */
    private function getAvailableStates()
    {
        return array(
            self::STATE_COMMERCE_CHECKOUT,
            self::STATE_COMMERCE_SOLD,
            self::STATE_COMMERCE_RETURNED,
            self::STATE_COMMERCE_RETURNED_PARTIAL,
            self::STATE_COMMERCE_RESERVERD,
            self::STATE_COMMERCE_BACK_ORDERED,
            self::STATE_ORDERED_PENDING,
            self::STATE_ORDERED_RECEIVED,
            self::STATE_ORDERED_RECEIVED_PARTIAL,
            self::STATE_INVENTORY_ONHOLD,
            self::STATE_INVENTORY_RELEASED,
            self::STATE_INVENTORY_RELEASED_PARTIAL,
            self::STATE_CANCELLED,
            self::STATE_OPENED,
        );
    }
}