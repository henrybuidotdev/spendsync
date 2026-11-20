<?php

namespace Database\Seeders;

use App\Enums\Direction;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * About three and a half months of believable spending for the demo account: an office worker in Ho Chi Minh City
 * with a salary, rent, bills, daily coffee and Grab, weekend dinners, online shopping and a few one-off events.
 * Seeded randomness, so every run produces the same ledger. Dates end on $until; spendsync:refresh-demo keeps them current.
 */
class DemoLedger
{
    private const COFFEE = [['HIGHLANDS COFFEE', 39, 69], ['PHUC LONG', 45, 75], ['THE COFFEE HOUSE', 45, 69], ['KATINAT', 49, 79]];

    private const DELIVERY = [['GRABFOOD', 45, 120], ['SHOPEEFOOD', 40, 110], ['BE FOOD', 45, 95]];

    private const RESTAURANTS = [['NHA HANG NGON', 250, 600], ['PIZZA 4PS', 450, 900], ['GOGI HOUSE', 500, 900], ['LAU PHAN', 350, 700], ['SUSHI HOKKAIDO SACHI', 500, 1000]];

    private const SHOPEE = [['Tai nghe bluetooth', 459], ['Op lung dien thoai', 89], ['Ao thun basic', 199], ['Binh giu nhiet', 245], ['Sach "Atomic Habits"', 139], ['Day sac Type-C', 79], ['Ke de laptop', 329]];

    private Randomizer $random;

    /** @var array<string, int> */
    private array $categories;

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    public function __construct()
    {
        $this->random = new Randomizer(new Mt19937(2026));
        $this->categories = Category::system()->pluck('id', 'name')->all();
    }

    /** @return list<array<string, mixed>> */
    public function build(CarbonImmutable $until): array
    {
        $until = $until->setTimezone(config('app.display_timezone'))->endOfDay();
        $start = $until->subMonthsNoOverflow(3)->startOfMonth();

        for ($day = $start; $day->lte($until); $day = $day->addDay()) {
            $this->monthly($day, $until);
            $this->daily($day);
        }

        $this->oneOffs($start);

        return array_values(array_filter($this->rows, fn (array $row) => $row['occurred_at']->lte($until)));
    }

    /** Monthly items; salary and rent for the final month come from the imported bank emails. */
    private function monthly(CarbonImmutable $day, CarbonImmutable $until): void
    {
        $isFinalMonth = $day->isSameMonth($until);

        match ($day->day) {
            1 => $this->add($day->setTime(9, 15), 'out', 245000, 'Internet bill', 'FPT TELECOM', 'THANH TOAN CUOC INTERNET FPT', 'CAKE'),
            3 => $this->add($day->setTime(20, 40), 'out', 260000, 'TV & streaming', 'NETFLIX.COM', 'NETFLIX.COM SINGAPORE', 'VPB'),
            5 => $this->add($day->setTime(8, 0), 'out', 690000, 'Gym & fitness', 'CALIFORNIA FITNESS & YOGA', 'GIA HAN HOI VIEN THANG', 'VPB'),
            default => null,
        };

        if ($day->day === 7 && ! $isFinalMonth) {
            $this->add($day->setTime(9, 30), 'in', 25000000, 'Salary', 'CONG TY TNHH CONG NGHE ABC', 'CONG TY TNHH CONG NGHE ABC TRA LUONG', 'VCB');
        }

        if ($day->day === 8) {
            $this->add($day->setTime(19, 5), 'out', 2000000, 'Transfers', 'NGUYEN THI MAI', 'Gui me tien tieu', 'VCB', note: 'Gửi mẹ');
            $this->add($day->setTime(19, 20), 'out', 3000000, 'Investment', 'CTCP CHUNG KHOAN VNDIRECT', 'NOP TIEN TAI KHOAN CHUNG KHOAN', 'VCB');
        }

        if ($day->day === 11 && ! $isFinalMonth) {
            $this->add($day->setTime(20, 15), 'out', 5500000, 'Rentals', 'LE THI HOA', 'TIEN NHA', 'VCB');
        }

        match ($day->day) {
            14 => $this->add($day->setTime(10, 5), 'out', $this->money(620, 1150), 'Electricity bill', 'EVN HCMC', 'THANH TOAN TIEN DIEN', 'CAKE'),
            15 => $this->add($day->setTime(10, 10), 'out', $this->money(110, 165), 'Water bill', 'SAWACO', 'THANH TOAN TIEN NUOC', 'CAKE'),
            17 => $this->add($day->setTime(12, 0), 'out', 59000, 'TV & streaming', 'SPOTIFY', 'SPOTIFY P2B3C4D5E6 STOCKHOLM', 'VPB'),
            20 => $this->add($day->setTime(18, 30), 'out', 1500000, 'Cash withdrawal', null, 'RUT TIEN ATM VCB TOWER', 'VCB'),
            default => null,
        };
    }

    private function daily(CarbonImmutable $day): void
    {
        $isWeekend = $day->isWeekend();

        if (! $isWeekend && $this->chance(65)) {
            [$shop, $min, $max] = $this->pick(self::COFFEE);
            $this->add($day->setTime(7 + $this->int(0, 2), $this->int(0, 59)), 'out', $this->money($min, $max), 'Coffee', $shop, "THANH TOAN QR {$shop}", $this->pick(['VPB', 'CAKE']));
        }

        if (! $isWeekend && $this->chance(45)) {
            [$app, $min, $max] = $this->pick(self::DELIVERY);
            $this->add($day->setTime(11, $this->int(20, 59)), 'out', $this->money($min, $max), 'Delivery', $app, "{$app} ORDER", 'CAKE');
        } elseif (! $isWeekend && $this->chance(60)) {
            $this->add($day->setTime(12, $this->int(0, 30)), 'out', $this->money(35, 55), 'Food & drink', 'Com tam Ba Ghien', null, Transaction::MANUAL);
        }

        if ($this->chance($isWeekend ? 45 : 35)) {
            $this->add($day->setTime($this->int(8, 21), $this->int(0, 59)), 'out', $this->money(28, 135), 'Taxi', 'GRAB', 'GRAB*TRANSPORT HO CHI MINH', 'VPB');
        }

        if ($isWeekend && $this->chance(35)) {
            [$restaurant, $min, $max] = $this->pick(self::RESTAURANTS);
            $this->add($day->setTime(19, $this->int(0, 45)), 'out', $this->money($min, $max), 'Restaurants', $restaurant, "THANH TOAN THE {$restaurant}", 'VCB');
        }

        if ($day->isSaturday()) {
            $this->add($day->setTime(10, $this->int(0, 50)), 'out', $this->money(250, 600), 'Food & drink', $this->pick(['BACH HOA XANH', 'WINMART', 'CO.OP MART']), 'THANH TOAN DI CHO', 'VPB');
        }

        if ($day->isSunday() && $this->chance(50)) {
            $this->add($day->setTime(16, 30), 'out', $this->money(70, 100), 'Vehicle maintenance', 'PETROLIMEX CHXD SO 12', 'DO XANG', Transaction::MANUAL);
        }

        if ($this->chance(14)) {
            [$item, $price] = $this->pick(self::SHOPEE);
            $this->add($day->setTime(22, $this->int(0, 59)), 'out', $price * 1000, 'Shopping', 'SHOPEE', "SHOPEEPAY {$item}", 'CAKE');
        }

        if ($isWeekend && $this->chance(18)) {
            $this->add($day->setTime(20, 10), 'out', $this->money(190, 260), 'Movies', 'CGV CINEMAS', 'CGV VINCOM DONG KHOI 2 VE', 'VPB');
        }

        if ($this->chance(4)) {
            $this->add($day->setTime(15, 20), 'out', $this->money(150, 450), null, $this->pick(['TRAN MINH KHOA', 'PHAM THU TRANG', 'VO HOANG NAM']), 'chuyen tien', 'VCB');
        }
    }

    private function oneOffs(CarbonImmutable $start): void
    {
        $this->add($start->addDays(9)->setTime(14, 0), 'out', 1850000, 'Flights', 'VIETJET AIR', 'VE MAY BAY SGN-DAD', 'VPB', note: 'Đi Đà Nẵng cuối tuần');
        $this->add($start->addDays(12)->setTime(11, 0), 'out', 2400000, 'Restaurants', 'HAI SAN BE MAN', 'THANH TOAN THE HAI SAN BE MAN', 'VCB', note: 'Ăn hải sản ở Đà Nẵng');
        $this->add($start->addDays(18)->setTime(21, 0), 'out', 1000000, 'Wedding', 'LE QUANG HUY', 'Mung cuoi Huy va Ngoc', 'VCB', note: 'Đám cưới Huy');
        $this->add($start->addDays(24)->setTime(9, 40), 'out', 450000, 'Doctor & hospital', 'BENH VIEN DA KHOA TAM ANH', 'KHAM BENH', 'VCB');
        $this->add($start->addDays(24)->setTime(10, 30), 'out', 186000, 'Medicine', 'NHA THUOC LONG CHAU', 'THANH TOAN QR LONG CHAU', 'CAKE');
        $this->add($start->addDays(33)->setTime(16, 0), 'out', 2000000, 'Lending', 'PHAM THU TRANG', 'Cho Trang muon', 'VCB', note: 'Trang hẹn trả cuối tháng');
        $this->add($start->addDays(41)->setTime(19, 45), 'in', 1200000, 'Sales', 'NGUYEN DUC TAI', 'Mua ban phim co cu', 'CAKE', note: 'Bán bàn phím cũ trên Chợ Tốt');
        $this->add($start->addDays(47)->setTime(14, 20), 'in', 199000, 'Refund', 'SHOPEE', 'HOAN TIEN DON HANG SHOPEE', 'CAKE');
        $this->add($start->addDays(52)->setTime(10, 0), 'out', 350000, 'Vehicle maintenance', 'HEAD HONDA PHAT TIEN', 'BAO DUONG XE', Transaction::MANUAL);
        $this->add($start->addDays(58)->setTime(20, 0), 'in', 2000000, 'Debt collection', 'PHAM THU TRANG', 'Tra tien muon', 'VCB');
        $this->add($start->addDays(63)->setTime(18, 0), 'in', 3500000, 'Other income', 'STUDIO MOC', 'THANH TOAN THIET KE LOGO', 'VCB', note: 'Freelance thiết kế logo');
        $this->add($start->addDays(70)->setTime(19, 30), 'out', 1300000, 'Music & concerts', 'TICKETBOX', 'VE CONCERT ANH TRAI SAY HI', 'VPB');
        $this->add($start->addDays(76)->setTime(13, 15), 'out', 799000, 'Shopping', 'UNIQLO DONG KHOI', 'THANH TOAN THE UNIQLO', 'VPB');
        $this->add($start->addDays(81)->setTime(17, 40), 'out', 420000, 'Beauty', 'GUARDIAN', 'THANH TOAN QR GUARDIAN', 'CAKE');
        $this->add($start->addDays(88)->setTime(20, 0), 'out', 500000, 'Gifts & Donations', 'QUY VI NGUOI NGHEO', 'Ung ho mien Trung', 'VCB');
    }

    private function add(CarbonImmutable $at, string $direction, int $amount, ?string $category, ?string $counterparty, ?string $description, string $bank, ?string $note = null): void
    {
        $this->rows[] = [
            'bank_code' => $bank,
            'direction' => Direction::from($direction),
            'amount' => $amount,
            'counterparty' => $counterparty,
            'description' => $bank === Transaction::MANUAL ? null : $description,
            'occurred_at' => $at->utc(),
            'category_id' => $category === null ? null : $this->categories[$category],
            'note' => $note,
        ];
    }

    private function chance(int $percent): bool
    {
        return $this->int(1, 100) <= $percent;
    }

    private function int(int $min, int $max): int
    {
        return $this->random->getInt($min, $max);
    }

    /** A price in thousands of đồng, rounded to the nearest 1.000₫. */
    private function money(int $minThousands, int $maxThousands): int
    {
        return $this->int($minThousands, $maxThousands) * 1000;
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[$this->int(0, count($items) - 1)];
    }
}
