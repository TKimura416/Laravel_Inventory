<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Stevebauman\Inventory\Models\Category;
use Stevebauman\Inventory\Models\Inventory;
use Stevebauman\Inventory\Models\InventorySku;

class InventorySkuTest extends InventoryTest
{
    protected function newInventorySku()
    {
        $item = $this->newInventory();

        return $item->generateSku();
    }

    public function testInventorySkuGeneration()
    {
        /*
         * SKU generation is enabled
         */
        Config::shouldReceive('get')->once()->andReturn(true);

        /*
         * SKU code limit
         */
        Config::shouldReceive('get')->once()->andReturn(6);

        /*
         * SKU prefix limit
         */
        Config::shouldReceive('get')->once()->andReturn(3);

        /*
         * SKU separator
         */
        Config::shouldReceive('get')->once()->andReturn('');

        DB::shouldReceive('beginTransaction')->once()->shouldReceive('commit')->once();

        Event::shouldReceive('fire')->once();

        $sku = $this->newInventorySku();

        $this->assertEquals(1, $sku->inventory_id);
        $this->assertEquals('DRI000001', $sku->code);
    }

    public function testInventorySkuGenerationForSmallCategoryName()
    {
        $item = $this->newInventory();

        $category = Category::find(1);

        $update = array(
            'name' => 'D',
        );

        $category->update($update);

        /*
         * SKU generation is enabled
         */
        Config::shouldReceive('get')->once()->andReturn(true);

        /*
         * SKU code limit
         */
        Config::shouldReceive('get')->once()->andReturn(6);

        /*
         * SKU prefix limit
         */
        Config::shouldReceive('get')->once()->andReturn(3);

        /*
         * SKU separator
         */
        Config::shouldReceive('get')->once()->andReturn('');

        /*
         * Generate the SKU
         */
        $item->generateSku();

        /*
         * Get the sku code
         */
        $sku = $item->sku()->first()->code;

        $this->assertEquals('D000001', $sku);
    }

    public function testInventorySkuRegeneration()
    {
        $this->newInventorySku();

        $item = Inventory::find(1);

        /*
         * SKU code limit
         */
        Config::shouldReceive('get')->once()->andReturn(6);

        /*
         * SKU prefix limit
         */
        Config::shouldReceive('get')->once()->andReturn(3);

        DB::shouldReceive('beginTransaction')->once()->shouldReceive('commit')->once();

        $item->regenerateSku();

        $sku = InventorySku::first();

        $this->assertEquals($sku->id, 2);
    }

    public function testInventoryHasSku()
    {
        $this->newInventorySku();

        $item = Inventory::find(1);

        $this->assertTrue($item->hasSku());
    }

    public function testInventoryDoesNotHaveSku()
    {
        $this->newInventorySku();

        $sku = InventorySku::first();
        $sku->delete();

        $item = Inventory::find(1);

        $this->assertFalse($item->hasSku());
    }

    public function testInventorySkuGenerationFalse()
    {
        $item = $this->newInventory();

        $item->category_id = NULL;
        $item->save();

        $this->assertFalse($item->generateSku());
    }

    public function testInventoryGetSku()
    {
        $this->testInventorySkuGeneration();

        $item = Inventory::find(1);

        $expected = 'DRI000001';

        $this->assertEquals($expected, $item->sku->code);
        $this->assertEquals($expected, $item->getSku());
    }

    public function testInventoryFindBySku()
    {
        $this->testInventorySkuGeneration();

        $item = Inventory::findBySku('DRI000001');

        $this->assertEquals('Milk', $item->name);
    }

    public function testInventorySkuBlankCategoryName()
    {
        $this->testInventorySkuGeneration();

        $category = Category::find(1);

        $category->update(array('name' => '     '));

        $item = Inventory::find(1);

        /*
         * SKU generation is enabled
         */
        Config::shouldReceive('get')->once()->andReturn(true);

        /*
         * SKU code limit
         */
        Config::shouldReceive('get')->once()->andReturn(6);

        /*
         * SKU prefix limit
         */
        Config::shouldReceive('get')->once()->andReturn(3);

        /*
         * SKU separator
         */
        Config::shouldReceive('get')->once()->andReturn('');

        $sku = $item->regenerateSku();

        /*
         * SKU generation will fail and the previous will be restored
         * with new ID
         */
        $this->assertEquals(2, $sku->id);
        $this->assertEquals('DRI000001', $sku->code);
    }

    public function testInventorySkuSeparator()
    {
        $this->testInventorySkuGeneration();

        /*
         * SKU generation is enabled
         */
        Config::shouldReceive('get')->once()->andReturn(true);

        /*
         * SKU code limit
         */
        Config::shouldReceive('get')->once()->andReturn(6);

        /*
         * SKU prefix limit
         */
        Config::shouldReceive('get')->once()->andReturn(3);

        /*
         * SKU separator
         */
        Config::shouldReceive('get')->once()->andReturn('-');

        $item = Inventory::find(1);

        $sku = $item->regenerateSku();

        $this->assertEquals(2, $sku->id);
        $this->assertEquals('DRI-000001', $sku->code);
    }
}