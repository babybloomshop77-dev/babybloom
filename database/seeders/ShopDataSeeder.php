<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Начальные данные, перенесённые из таблиц BabyBloom.
 */
class ShopDataSeeder extends Seeder
{
    private const PDD = 'https://mobile.yangkeduo.com/goods.html?goods_id=';

    public function run(): void
    {
        $this->pinduoduo();
        $this->wholesale();
        $this->orders();
        $this->sales();
    }

    private function d(string $date): string
    {
        return Carbon::createFromFormat('d.m.Y', $date)->format('Y-m-d');
    }

    private function status(?string $raw): array
    {
        return match (true) {
            $raw === 'TRUE' => ['sold', null],
            $raw === 'processing' => ['processing', null],
            $raw !== null && $raw !== '' && $raw !== '-' => ['reserved', $raw],
            default => ['in_stock', null],
        };
    }

    private function pinduoduo(): void
    {
        // name, url, qty, price_amd (всего), price_yuan, sell, shipping (всего), date
        $rows = [
            ['marvel', self::PDD.'815278236756', 2, 2080, 38.42, 6500, 2200, '17.10.2026'],
            ['brown verx', self::PDD.'613049137633', 2, 1529, 28.24, 5500, 2100, '17.10.2026'],
            ['vilvetic shalvar', self::PDD.'539716254446', 2, 1416, 26.16, 5900, 1600, '17.10.2026'],
            ['jins', self::PDD.'263010153713', 2, 2846, 52.57, 5900, 2100, '17.10.2026'],
            ['leral verev nerqev', self::PDD.'658955430902', 2, 3092, 57.1, 9500, 2300, '17.10.2026'],
            ['polo spartivka sumkov', self::PDD.'778256869770', 2, 5317, 98.2, 9900, 4200, '17.10.2026'],
            ['jaki-juki jinsov verev nerqev', self::PDD.'726815723230', 2, 6498, 120, 11900, 4300, '17.10.2026'],
            ['erku guynani sviter', self::PDD.'899180314624', 2, 5557, 101.6, 7900, 4100, '17.10.2026'],
            ['uggi', 'https://mobile.yangkeduo.com/goods.html?ps=YVDfXJDVYp', 2, 2627, 48.52, 8500, 2300, '17.10.2026'],
            ['derya, 2 hatanoc', self::PDD.'636340782386', 2, 4971, 91.8, 7900, 3900, '19.10.2026'],
            ['axjka brown set', self::PDD.'656701768986', 2, 7066, 130.5, 12500, 6300, '17.10.2026'],
            ['axjka dvoyka pstik napastakov', self::PDD.'984649916176', 2, 5631, 104, 10900, 4700, '17.10.2026'],
            ['Shushi @ntrac@', self::PDD.'530850352107', 1, 2815, 52, 8500, 1500, '17.10.2026'],
            ['body carters', self::PDD.'957062430043', 1, 3509, 64.8, null, 1000, '13.09.2026'],
            ['polo seri spartivka', self::PDD.'796812154957', 1, 1911, 35.3, 6500, 0, '13.09.2026'],
            ['mamas papas kombinizon', self::PDD.'955709934509', 2, 8208, 151.6, 9000, 5600, '13.09.2026'],
            ['mamas papas pijama', self::PDD.'697596826333', 1, 2707, 50, 4500, 1700, '17.10.2026'],
            ['broshka', self::PDD.'924009141796', 1, 503, 9.3, null, 0, '17.10.2026'],
        ];

        $products = [];
        foreach ($rows as [$name, $url, $qty, $amd, $yuan, $sell, $ship, $date]) {
            $product = Product::create([
                'name' => $name,
                'source' => 'pinduoduo',
                'pinduoduo_url' => $url,
                'buy_price_yuan' => round($yuan / $qty, 2),
                'buy_price_amd' => (int) round($amd / $qty),
                'shipping_cost' => (int) round($ship / $qty),
                'sell_price' => $sell,
            ]);
            $purchase = Purchase::create([
                'product_id' => $product->id,
                'title' => $name,
                'source' => 'pinduoduo',
                'url' => $url,
                'quantity' => $qty,
                'price_amd' => $amd,
                'price_yuan' => $yuan,
                'shipping_cost' => $ship,
                'purchased_at' => $this->d($date),
            ]);
            $products[$name] = [$product, $purchase];
        }

        // product, note, size, sell, color, status
        $stock = [
            ['marvel', null, '100 2–3 года 36 սմ, 52 սմ', 6500, 'black', null],
            ['marvel', null, '100 2–3 года', 6500, 'black', null],
            ['marvel', null, '110 3–4 года', 6500, 'blue', null],
            ['marvel', null, '110 3–4 года', 6500, 'blue', null],
            ['brown verx', null, '100 2–3 года', 5500, 'cream', null],
            ['brown verx', null, '100 2–3 года', 5500, 'cream', null],
            ['brown verx', null, '110 3–4 года', 5500, 'Brown', null],
            ['brown verx', null, '110 3–4 года', 5500, 'Brown', null],
            ['vilvetic shalvar', null, '100 3–4 года', 5900, 'dark gray', null],
            ['vilvetic shalvar', null, '100 3–4 года', 5900, 'dark gray', null],
            ['vilvetic shalvar', null, '90 2–3 года', 5900, 'brown', null],
            ['vilvetic shalvar', null, '90 2–3 года', 5900, 'brown', 'TRUE'],
            ['jins', null, '100 2–3 года', 5900, 'light blue', null],
            ['jins', null, '100 2–3 года', 5900, 'light blue', null],
            ['jins', null, '110 3–4 года', 5900, 'black', null],
            ['jins', null, '110 3–4 года', 5900, 'black', null],
            ['leral verev nerqev', null, '100 2–3 года', 9500, 'brown', '-'],
            ['leral verev nerqev', null, '100 2–3 года', 9500, 'brown', null],
            ['leral verev nerqev', null, '110 3–4 года', 9500, 'blue', 'TRUE'],
            ['leral verev nerqev', null, '110 3–4 года', 9500, 'blue', 'TRUE'],
            ['polo spartivka sumkov', null, '100 2–3 года Բլուզ 36 սմ Տաբատ 50 սմ', 9900, 'white', 'processing'],
            ['polo spartivka sumkov', null, '100 2–3 года Բլուզ 36 սմ Տաբատ 50 սմ', 9900, 'white', 'processing'],
            ['polo spartivka sumkov', null, '110 3–4 года Բլուզ 38 սմ Տաբատ 54 սմ', 9900, 'black', null],
            ['polo spartivka sumkov', null, '110 3–4 года Բլուզ 38 սմ Տաբատ 54 սմ', 9900, 'black', null],
            ['jaki-juki jinsov verev nerqev', null, '90 1–2 года', 11900, 'coffee', null],
            ['jaki-juki jinsov verev nerqev', null, '90 1–2 года', 11900, 'coffee', null],
            ['jaki-juki jinsov verev nerqev', null, '110 3–4 года', 11900, 'black', null],
            ['jaki-juki jinsov verev nerqev', null, '110 3–4 года', 11900, 'black', null],
            ['erku guynani sviter', null, '100 2–3 года', 7900, 'brown+apricot', null],
            ['erku guynani sviter', null, '100 2–3 года', 7900, 'brown+apricot', null],
            ['erku guynani sviter', null, '100 2–3 года', 7900, 'black', null],
            ['erku guynani sviter', null, '110 3–4 года', 7900, 'brown+apricot', null],
            ['erku guynani sviter', null, '110 3–4 года', 7900, 'gray', null],
            ['uggi', null, '15/ 12.5sm', 8500, 'brown', 'TRUE'],
            ['uggi', null, '16/ 13sm', 8500, 'brown', 'TRUE'],
            ['uggi', null, '17/ 13.5sm', 8500, 'brown', 'TRUE'],
            ['uggi', null, '18/ 14sm', 8500, 'brown', 'TRUE'],
            ['derya, 2 hatanoc', 'rozvi klubnikov', '90 1–2 года', 7900, 'yellow', null],
            ['derya, 2 hatanoc', null, '90 1–2 года', 7900, 'pink', null],
            ['derya, 2 hatanoc', 'orange pumpuliknerov', '100 2–3 года 46 սմ', 7900, 'orange', null],
            ['derya, 2 hatanoc', 'napastakov', '100 2–3 года', 7900, 'yellow', null],
            ['axjka brown set', null, '100 2–3 года', 12500, 'brown', 'TRUE'],
            ['axjka brown set', null, '100 2–3 года', 12500, 'brown', null],
            ['axjka brown set', null, '110 3–4 года', 12500, 'brown', null],
            ['axjka brown set', null, '110 3–4 года', 12500, 'brown', 'Goqi mot'],
            ['axjka dvoyka pstik napastakov', null, '100 2–3 года բլուս` 34 սմ տաբատ` 50 սմ', 10900, 'green', 'processing'],
            ['axjka dvoyka pstik napastakov', null, '100 2–3 года', 10900, 'green', null],
            ['Shushi @ntrac@', null, '100 2–3 года 39 սմ', 8500, 'caramel', null],
            ['mamas papas kombinizon', null, '9-12', 9000, 'txu', null],
            ['mamas papas kombinizon', null, '9-12', 9000, 'axjka', 'TRUE'],
            ['mamas papas pijama', 'dexin', '18-24M', 4500, 'axjka', null],
            ['mamas papas pijama', 'rozvi', '18-24M', 4500, 'axjka', null],
        ];

        foreach ($stock as [$name, $note, $size, $sell, $color, $raw]) {
            [$product, $purchase] = $products[$name];
            [$status, $reservedFor] = $this->status($raw);
            StockItem::create([
                'product_id' => $product->id,
                'purchase_id' => $purchase->id,
                'size' => $size,
                'color' => $color,
                'buy_price' => $purchase->unit_cost,
                'sell_price' => $sell,
                'status' => $status,
                'reserved_for' => $reservedFor,
                'arrived_at' => '2026-11-05',
                'note' => $note,
            ]);
        }

        $this->fillSizesAndColors();
    }

    private function wholesale(): void
    {
        // product, size, buy, sell, color, date, status
        $rows = [
            ['Polo spartivka', 'M minchev 2 tarekan', 6500, 9900, 'Blue', '11.10.2026', 'TRUE'],
            ['Polo spartivka', 'S 1 tarekan (Ժակետ` 30սմ, Տաբատ` 42սմ)', 6500, 9900, 'Blue', '11.10.2026', 'TRUE'],
            ['Polo spartivka', 'L 2-3 tarekan', 6500, 9900, 'Blue', '11.10.2026', 'TRUE'],
            ['Polo spartivka', 'S 1 tarekan (Ժակետ` 30սմ, Տաբատ` 42սմ)', 6500, 9900, 'Gray', '11.10.2026', 'TRUE'],
            ['Polo spartivka', 'M minchev 2 tarekan', 6500, 9900, 'Gray', '11.10.2026', 'TRUE'],
            ['Polo spartivka', 'L 2-3 tarekan', 6500, 9900, 'Gray', '11.10.2026', 'TRUE'],
            ['Polo spartivka', 'XL 3-4 tarekan', 6500, 9900, 'Gray', '11.10.2026', 'TRUE'],
            ['Polo spartivka', 'S 1 tarekan (Ժակետ` 30սմ, Տաբատ` 42սմ)', 6500, 9900, 'Burgundi', '11.10.2026', null],
            ['Axjka dexini mej shorer Napastakov', 'size 5, 1 tarekan', 5000, 8500, 'Yellow', '11.10.2026', 'TRUE'],
            ['Axjka dexini mej shorer Napastakov', 'size 13, 5 tarekan(բլուզ` 36սմ, տաբատ`50 սմ)', 5000, 8500, 'Yellow', '11.10.2026', null],
            ['Sirun teverov', 'size 07, 1-2 tarekan', 5000, 8500, 'Yellow', '11.10.2026', 'TRUE'],
            ['Sirun teverov', 'size 11 4 tarekan', 5000, 8500, 'Yellow', '11.10.2026', 'TRUE'],
            ['Karshnvi srtikov', 'size 07, 1-2 tarekan (բլուզ`35սմ, տաբատ` 43սմ)', 5000, 8500, 'Brown', '11.10.2026', 'TRUE'],
            ['Derya sunk', 'size 80, 1-1,5', 4500, 7900, 'Cream', '11.10.2026', 'TRUE'],
            ['Derya sunk', 'size 90, 2-3', 4500, 7900, 'Cream', '11.10.2026', 'TRUE'],
            ['Derya Karmrot', 'size 66, 6m', 4500, 7900, 'Red', '11.10.2026', 'processing'],
            ['Derya Karmrot', 'size 73, 1-1.5 tarekan', 4500, 7900, 'Red', '11.10.2026', 'TRUE'],
            ['Verev nerqev axjka rozvi', 'size 1-1.5 tarekan', 3500, 6900, 'Pink', '11.10.2026', 'TRUE'],
            ['Verev nerqev axjka sev spitak', 'size 73, 1-1.5 tarekan', 4000, 6900, 'Black/White', '11.10.2026', 'TRUE'],

            ['Polo spartivka', 'M minchev 2 tarekan', 6500, 9900, 'Blue', '18.10.2026', 'TRUE'],
            ['Polo spartivka', 'XL 3-4 tarekan (Ժակետ` 36,5սմ, Տաբատ` 50սմ)', 6500, 9900, 'Gray', '18.10.2026', 'TRUE'],
            ['Polo spartivka', 'L 2-3 tarekan (Ժակետ` 36սմ, Տաբատ` 46,5 սմ)', 6500, 9900, 'Gray', '18.10.2026', 'TRUE'],
            ['Polo spartivka', 'L 2-3 tarekan (Ժակետ` 36սմ, Տաբատ` 46,5 սմ)', 6500, 9900, 'Gray', '18.10.2026', 'TRUE'],
            ['Polo spartivka', 'L 2-3 tarekan (Ժակետ` 36սմ, Տաբատ` 46,5 սմ)', 6500, 9900, 'Gray', '18.10.2026', 'TRUE'],
            ['Axjka bordo verev nerqev', 'size 100 մոտ 3–4 տարեկան (բլուզ`31սմ, տաբատ` 49սմ)', 5200, 8500, 'Burgundi', '18.10.2026', null],
            ['Axjka rozvi verev nerqev', 'size 120 6–7 տարեկան', 5200, 8500, 'Pink', '18.10.2026', 'TRUE'],
            ['Axjka rozvi verev nerqev', 'size 130 8–9 տարեկան', 5200, 8500, 'Pink', '18.10.2026', null],
            ['Axjka sev/spitak verev nerqev', 'size 110 4–5 տարեկան', 5200, 8500, 'Black/white', '18.10.2026', 'TRUE'],
            ['Axjka sev/spitak verev nerqev', 'size 120 6–7 տարեկան', 5200, 8500, 'Black/white', '18.10.2026', 'TRUE'],
            ['Axjka verx bac kapuyt', 'size 80', 3500, 6500, 'Blue', '18.10.2026', 'TRUE'],
            ['Axjka verx bac kapuyt', 'size 73', 3500, 6500, 'Blue', '18.10.2026', 'TRUE'],
            ['Axjka verx rozvi', 'size 66', 3500, 6500, 'Pink', '18.10.2026', 'TRUE'],
            ['Axjka verx rozvi', 'size 90 1,5–2 տարեկան', 3500, 6500, 'Pink', '18.10.2026', null],
            ['Polo kombinizon', 'size 66', 3800, 7900, 'Brown', '18.10.2026', null],
            ['Polo kombinizon', 'size 73', 3800, 7900, 'Brown', '18.10.2026', 'Goqi mot'],
            ['Polo kombinizon', 'size 90 1,5–2 տարեկան', 3800, 7900, 'Brown', '18.10.2026', null],
            ['Polo kombinizon', 'size 80', 3800, 7900, 'Brown', '18.10.2026', 'Goqi mot'],
            ['Axjka jinsov verev nerqev', 'L Բլուզ` 36 սմ Տաբատ` 52սմ', 5600, 9500, 'Cream', '18.10.2026', null],
        ];
        // Glxarkner: 6 шт за 7200 -> 1200 за штуку, продажа 2000
        for ($i = 0; $i < 6; $i++) {
            $rows[] = ['Glxarkner', null, 1200, 2000, 'Multi', '18.10.2026', null];
        }

        $purchases = [];
        foreach (collect($rows)->groupBy(5) as $date => $items) {
            $purchases[$date] = Purchase::create([
                'title' => "Оптовик {$date}",
                'source' => 'wholesale',
                'quantity' => $items->count(),
                'price_amd' => $items->sum(2),
                'shipping_cost' => 0,
                'purchased_at' => $this->d($date),
            ]);
        }

        $products = [];
        foreach ($rows as [$name, $size, $buy, $sell, $color, $date, $raw]) {
            $products[$name] ??= Product::create([
                'name' => $name,
                'source' => 'wholesale',
                'buy_price_amd' => $buy,
                'shipping_cost' => 0,
                'sell_price' => $sell,
            ]);
            [$status, $reservedFor] = $this->status($raw);

            StockItem::create([
                'product_id' => $products[$name]->id,
                'purchase_id' => $purchases[$date]->id,
                'size' => $size,
                'color' => $color,
                'buy_price' => $buy,
                'sell_price' => $sell,
                'status' => $status,
                'reserved_for' => $reservedFor,
                'arrived_at' => $this->d($date),
            ]);
        }

        $this->fillSizesAndColors();
    }

    /** Заполнить размеры/цвета товаров из склада */
    private function fillSizesAndColors(): void
    {
        foreach (Product::with('stockItems')->get() as $product) {
            if ($product->stockItems->isEmpty()) {
                continue;
            }
            $product->update([
                'sizes' => $product->stockItems->pluck('size')->filter()->unique()->values()->all(),
                'colors' => $product->stockItems->pluck('color')->filter()->unique()->values()->all(),
            ]);
        }
    }

    private function orders(): void
    {
        $rows = [
            ['arjuk kombinizon', 'Rom', 'instagram', 'Davtashen 2 poxoc, 8 shenq', '18.11.2026', '098-40-91-87', true, 5900],
            ['polo spitak, karshni gcov botaz', 'Tatev Hmayakyan', 'facebook', 'Adonc 2/4 5/1 144 bn', '19.11.2026', '093-56-56-83', false, 16400],
            ['axjka dvoyka pstik napastakov', 'Eva Abrahamyan', 'instagram', 'Tumanyan 13 michel salon', '19.11.2026', '077-85-14-14', false, 10900],
        ];

        foreach ($rows as [$items, $name, $channel, $address, $date, $phone, $done, $price]) {
            Order::create([
                'items' => $items,
                'customer_name' => $name,
                'channel' => $channel,
                'address' => $address,
                'ordered_at' => $this->d($date),
                'phone' => $phone,
                'is_done' => $done,
                'price' => $price,
            ]);
        }
    }

    private function sales(): void
    {
        // product, price, date, customer
        $rows = [
            ['tpac mayka', 5000, '08.10.2026', 'Saro'],
            ['uggi beute duck', 6900, '08.10.2026', null],
            ['jacket kombinizon', 4900, '09.10.2026', null],
            ['carters body (rozvi seri komplekt) 9m', 7900, '10.10.2026', null],
            ['carters kapuyt polosati troyka (9m)', 9000, '11.10.2026', null],
            ['kremvi jilet pinduduic (1-1.5 y)', null, '11.10.2026', null],
            ['Derya sunk (size 80, 1-1,5)', null, '11.10.2026', null],
            ['axjka troyka jiletov pinduduic (1-1.5 y)', null, '11.10.2026', null],
            ['carters body (kanach manach) 9m', 7900, '12.10.2026', null],
            ['Polo spartivka (S 1 tarekan gray)', 9900, '14.10.2026', null],
            ['Polo spartivka (XL 3-4 tarekan gray)', 9900, '14.10.2026', null],
            ['gcavor jaket pinduduic', 5900, '14.10.2026', null],
            ['Karshnvi srtikov, arjukov naxsherov verev nerqev, bej', 16000, '16.10.2026', null],
            ['spitak uggi, naski 6 zuyg', 10500, '16.10.2026', null],
            ['arinjic rozvi verev nerqev', 6900, '16.10.2026', null],
            ['arinjic sirun teverov', 8500, '16.10.2026', null],
            ['pinduduic seri kombinizon L, L', 4900, '16.10.2026', null],
            ['Polo spartivka (M minchev 2 tarekan blue)', 9900, '16.10.2026', null],
            ['Axjka dexini mej shorer Napastakov (size 5, 1 tarekan)', 8500, '16.10.2026', null],
            ['Verev nerqev axjka sev spitak (size 73, 1-1.5 tarekan)', 6900, '17.10.2026', null],
            ['Polo spartivka (L 2-3 tarekan blue)', 9900, '17.10.2026', null],
            ['kanach kurtka pinduduic', 7500, '17.10.2026', null],
            ['Mamas papas kombinizon rozvi', 9000, '18.10.2026', null],
            ['Mamas papas kombinizon rozvi kanachov', 9000, '18.10.2026', null],
            ['Polo spartivka (M minchev 2 tarekan)', 10000, '20.10.2026', null],
            ['araqman pox Goqin', 1500, '20.10.2026', null],
            ['papuk/ arjuk kombinizon bac guyn@', 6000, '20.10.2026', null],
            ['Axjka verx rozvi Arinjic', 6500, '21.10.2026', null],
            ['Polo spartivka (M minchev 2 tarekan) gray', 10000, '22.10.2026', null],
            ['Polo spartivka L 2-3 tarekan gray', 9900, '23.10.2026', null],
            ['Axjka verx bac kapuyt 80 size', 7000, '25.10.2026', null],
            ['glxark Vanin', 1200, '25.10.2026', null],
            ['Polo spartivka L 2-3 tarekan gray', 10000, '26.10.2026', null],
            ['axjka dvoyka aranc jilet pinduduic (1-1.5 y)', 7000, '26.10.2026', null],
            ['Polo spartivka L 2-3 tarekan gray', 9900, '26.10.2026', null],
            ['Polo spartivka XL 3-4 tarekan gray', 10000, '26.10.2026', null],
            ['Polo spartivka L 2-3 tarekan gray', 10000, '26.10.2026', null],
            ['Sirun teverov size 07, 1-2 tarekan', 8500, '26.10.2026', null],
            ['Sunk derya size 90, 2-3', 7900, '26.10.2026', null],
            ['Polo spartivka S 1 tarekan', 9900, '29.10.2026', null],
            ['papuk, akanjnerov verev nerqev spitak', 8000, '30.10.2026', null],
            ['Derya Karmrot size 73, 1-1.5 tarekan', 8000, '30.10.2026', null],
            ['Axjka verx bac kapuyt size 73', 6500, '01.11.2026', null],
            ['jacket kombinizon', 5000, '02.11.2026', null],
            ['adyal arjukov', 5500, '02.11.2026', null],
            ['nerqnak arjukov', 5000, '04.11.2026', null],
            ['adyal srtikov', 5500, '05.11.2026', null],
            ['bodyner', 8000, '05.11.2026', null],
            ['Axjka sev/spitak verev nerqev size 120 6–7 տարեկան', 9000, '06.11.2026', null],
            ['Axjka sev/spitak verev nerqev size 110 4-5 տարեկան', 8500, '07.11.2026', null],
            ['vilvetic shalvar 90 2–3 года', 6500, '08.11.2026', null],
            ['Axjka rozvi verev nerqev size 120 6–7 տարեկան', 8500, '09.11.2026', null],
            ['polo dexin spartivka', 6500, '10.11.2026', null],
            ['leral verev nerqev 110 3–4 года blue', 9500, '11.11.2026', null],
            ['leral verev nerqev 110 3–4 года blue', 9500, '11.11.2026', null],
            ['uggi 15/ 12.5sm', 8500, '11.11.2026', null],
            ['kurtka spitak', 7500, '11.11.2026', null],
            ['polo seri spartivka Aniin', 6500, '12.11.2026', null],
            ['Arinayi bodyner', 5000, '12.11.2026', null],
            ['uggi 17/ 13.5sm', 8500, '12.11.2026', null],
            ['uggi 16/ 13sm', 8500, '12.11.2026', null],
            ['spitak dream', 8900, '12.11.2026', null],
            ['axjka brown set 100', 12500, '16.11.2026', null],
            ['mamas&papas 9-12', 9000, '16.11.2026', null],
            ['uggi 18/14sm', 8500, '16.11.2026', null],
            ['glxark Vanin', 2000, '16.11.2026', null],
            ['kanach bluzka', 5900, '16.11.2026', null],
            ['Karshnvi srtikov', 8000, '17.11.2026', null],
            ['karmrot derya', 7900, '17.11.2026', null],
        ];

        foreach ($rows as [$product, $price, $date, $customer]) {
            Sale::create([
                'product' => $product,
                'price' => $price,
                'sold_at' => $this->d($date),
                'customer_name' => $customer,
            ]);
        }
    }
}
