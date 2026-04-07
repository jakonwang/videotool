<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\model\Product as ProductModel;
use app\model\ProductStyleItem as ItemModel;
use app\service\ProductStyleKeywordSearchService;
use app\service\VolcArkVisionConfig;
use app\service\VolcArkVisionService;

/**
 * 濠电姷鏁搁崑娑㈩敋椤撶喐鍙忓ù鍏兼綑绾惧潡鏌涘Δ鍐х闯闁荤喖鍋婂銊╂煃瑜滈崜鐔兼偘椤曗偓瀵€燁檨闁绘挶鍎甸弻娑㈠即閵忊剝閿梺鍛婃煥閻倸顫忓ú顏勭閹艰揪绲烘慨鍥⒑濞茶寮鹃柛鐘冲哺瀹曟岸骞掗幘鏉戝妳闂侀潧顭粻鎴﹀焵椤掑啫鐓愰柕鍥у楠炲洭鍩℃担杞版偅闂佽楠搁悘姘箾婵犲洤钃熼柨鐔哄Т绾惧吋绻濇繝鍌氼劉闁轰緡鍨跺娲箹閻愭彃顫囧銈冨劜閹瑰洭鐛幇鏉跨婵°倐鍋撴鐐灪娣囧﹪濡堕崒姘闂備椒绱粻鎴︽偉婵傜绠栫€瑰嫭澹嬮弸搴ㄧ叓閸ャ劍顥旀俊顐犲劚椤啴濡舵惔鈥茬盎闂備礁搴滅紞浣割嚕椤愩埄鍚嬪璺猴躬閸炲爼姊虹紒妯荤叆妞ゃ劌閰ｉ崺鈧い鎺戝€告禒閬嶆煛瀹€瀣М妤犵偞锚铻栭柍褜鍓熷畷鏉款潩鐠哄搫鎯為梺闈涢獜缁辨洜绮绘ィ鍐╃厱闁斥晛鍙愰幋鐘亾濮橆厽绀堢紒杈ㄥ笚濞煎繘濡搁妷銉︽嚈濠?
 */
class Search extends BaseController
{
    private function jsonOut(array $payload, int $httpCode = 200)
    {
        return json($payload, $httpCode, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
    }

    /**
     * POST multipart: file 濠电姷鏁搁崑鐐哄垂閸洖绠板Δ锝呭暙绾惧潡鏌曢崼婵愭Ч闁稿鍊块弻銊モ槈濡警浠鹃柣搴㈢瀹€鎼佸蓟閺囷紕鐤€闁哄洨鍊敐鍥╃＜?
     */
    public function searchByImage()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonOut(['code' => 1, 'msg' => 'Please upload image file', 'data' => null]);
        }
        $tmp = $file->getPathname();
        if (!is_readable($tmp)) {
            return $this->jsonOut(['code' => 1, 'msg' => 'Unable to read uploaded file', 'data' => null]);
        }

        $hint = trim((string) $this->request->post('hint', ''));

        if (!VolcArkVisionConfig::get()['enabled']) {
            return $this->jsonOut([
                'code' => 1,
                'msg' => 'Visual search engine is disabled. Configure Doubao API key and model in settings.',
                'data' => null,
            ]);
        }

        return $this->searchByVolcArkVision($tmp, $hint);
    }

    /**
     * 闂傚倸鍊峰ù鍥敋瑜嶉湁婵娉涚壕濠氭煕閺囥劌鐏犵紒鈧仦鍙ョ箚闁靛牆鎳忛崳瑙勪繆椤愶綆鐒介柣銉邯椤㈡﹢鎮欓弶鎴烆仩闂?Doubao-vision闂傚倸鍊烽悞锔锯偓绗涘懐鐭欓柟鐑橆殕閸ゅ苯螖閿濆懎鏆欓柣顓燁殜閻擃偊宕堕妸褉濮囬梺鎼炲€曢敃銉╁Φ閸曨喚鐤€闁圭偓鍓氭禒濂告偠濮樺崬鏋涙慨濠冩そ濡啫鈽夊▎妯活棧濠电娀娼ч崐鐟邦潖婵犳艾绀嗛柟鐑橆殔鎯熼梺鎸庢濡嫰宕濋崨瀛樷拺闁革富鍘兼禍鐐箾閸忚偐鎳囬挊鐔兼煕閵夘喖澧柣鎾寸懇閹綊宕堕鍕缂備胶濮锋晶妤冩崲濞戙垹宸濇い鏂垮悑鐠囩偞绻濈喊妯哄⒉婵炲吋鐟╅、妯荤附缁嬪灝绐涘銈嗘尵婵厼危閸︻厾纾介柛灞捐壘閳ь剚鎮傚畷鎰亹閹烘挸浜遍梺鍝勬瘽妫颁焦鐫忛梻浣告贡椤牏鈧稈鏅濈槐鐐哄炊椤掍胶鍘甸梺璇″瀻閸愨晜鐦ｆ繝?+ 闂傚倷绀佸﹢閬嶅储瑜旈幃娲Ω閳轰胶顔囬梺褰掓？缁€渚€鎳滆ぐ鎺撳€甸梻鍫熺⊕閸熺偤鎮楀顓炲摵闁哄被鍔戝顕€宕堕…鎴烆棄闂備礁鎲￠幐鐑藉础閸愬樊娼栧┑鐘宠壘绾惧ジ鏌曢崼婵囧櫣缂佺姾顕ц灃闁绘﹢娼ф禒婊勪繆椤愶絿绠炵€殿噮鍋勯濂稿川椤忓拋娼旀繝纰樻閸ㄥ爼寮查弻銉ョ骇妞ゆ挶鍨洪悡鐔兼煟濡搫鏆卞┑顔煎€块弻娑欐償閵忕姷绁峰銈庝簻閸熸潙鐣烽幒妤佸€烽柤纰卞墰閸橆剚淇婇悙顏勨偓鏍偋濠婂牆纾绘繛鎴炵閺嗘粓鏌熼幆鏉啃撻柣?hint 闂傚倷娴囧畷鍨叏瀹ュ绀嬫い鎺戝€搁崵鎺楁⒒娴ｅ憡鍟炴い蹇旀倐瀹曞爼濡歌瀵櫕淇婇悙顏勨偓鏍涙担绯曟灃闁哄洨鍊ｉ敐澶娢у璺侯儑閸橀亶姊洪棃娑崇础闁告剬鍕垫綗闂傚倷绶氶埀顒傚仜閼活垶宕㈤幘顔界厱閻庯綆鍋呭畷宀勬煛?
     */
    private function searchByVolcArkVision(string $tmp, string $hint)
    {
        $cfg = VolcArkVisionConfig::get();
        [$match, $n] = $this->matchPhotoWithFullCatalog($tmp, $hint, $cfg);
        if ($n <= 0) {
            return $this->jsonOut([
                'code' => 1,
                'msg' => 'No catalog candidates available. Ensure active products have description/category/image.',
                'data' => ['engine' => 'volc_ark', 'catalog_size' => 0],
            ]);
        }

        if (!($match['ok'] ?? false)) {
            return $this->tryKeywordFallbackOrError($hint, $match, $n);
        }

        if (!empty($match['is_null'])) {
            return $this->tryKeywordFallbackOrNoMatch($hint, $n, 'No matching item found in full catalog (NULL)');
        }

        $code = trim((string) ($match['code'] ?? ''));
        if ($code === '') {
            return $this->tryKeywordFallbackOrNoMatch($hint, $n, 'Model returned an empty product code');
        }

        $item = $this->buildVolcArkItemForCode($code);
        if ($item === null) {
            return $this->tryKeywordFallbackOrNoMatch($hint, $n, 'Matched code does not map to an active record');
        }

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'items' => [$item],
                'engine' => 'volc_ark',
                'auto_match' => true,
                'matched_code' => $code,
                'num' => 1,
                'catalog_size' => $n,
            ],
        ]);
    }

    /**
     * 闂傚倸鍊烽懗鍫曗€﹂崼銏″床闁割偁鍎辩粈澶愭煙鏉堝墽鐣遍梻鍌ゅ灡缁绘稑顔忛鑽ょ泿缂佺偓宕橀～澶屾崲濞戙垹骞㈡俊顖濐嚙閻ㄦ垿姊虹粙娆惧剱闁规瓕顕ч銉╁礋椤掑倻鐦堥梺绋挎湰婢规洟宕戦幘瀵哥瘈婵﹩鍘鹃崢鐢告⒒閸屾氨澧涚紒瀣尵缁柨煤椤忓懐鍘卞┑鈽嗗灣椤牓鍩€椤掍胶绠炵€殿噮鍋勯濂稿川椤栨稒鐣烽梻浣告啞濞诧附绂嶉悙鍨潟婵鍩栭埛鎴犵磼鐎ｎ厽纭剁紒鐘冲缁辨帗寰勬繝鍕剁礊闂佸憡甯楃敮妤呭箚閺冨牆惟闁靛／灞拘ㄩ梺鑽ゅ枑缁孩鏅跺Δ鍐╂殰闁圭儤姊归弳婊堟煙閹澘袚闁?N 闂傚倸鍊风粈渚€骞栭位澶愭晸閻樿尙顓奸梺鍛婂姌瀵挻绔?
     *
     * @param array<string, mixed> $cfg
     * @return array{0:array<string, mixed>,1:int}
     */
    private function matchPhotoWithFullCatalog(string $tmp, string $hint, array $cfg): array
    {
        $batchSize = (int) ($cfg['max_catalog_items'] ?? 250);
        if ($batchSize < 10) {
            $batchSize = 10;
        } elseif ($batchSize > 500) {
            $batchSize = 500;
        }

        $page = 1;
        $catalogSize = 0;
        $candidateMap = [];
        $hintArg = $hint !== '' ? $hint : null;

        while (true) {
            $rows = ItemModel::where('status', 1)
                ->order('id', 'desc')
                ->page($page, $batchSize)
                ->select();
            if ($rows->isEmpty()) {
                break;
            }

            $catalog = $this->buildVolcArkCatalogFromRows($rows);
            $catalogSize += count($catalog);
            if ($catalog !== []) {
                $match = VolcArkVisionService::matchPhotoAutoWarehouse($tmp, $catalog, $hintArg);
                if (!($match['ok'] ?? false)) {
                    return [$match, $catalogSize];
                }
                $code = trim((string) ($match['code'] ?? ''));
                if ($code !== '') {
                    $candidateMap[$code] = true;
                }
            }
            ++$page;
        }

        if ($catalogSize <= 0) {
            return [['ok' => true, 'is_null' => true], 0];
        }

        $candidateCodes = array_keys($candidateMap);
        if ($candidateCodes === []) {
            return [['ok' => true, 'is_null' => true], $catalogSize];
        }
        if (count($candidateCodes) === 1) {
            return [['ok' => true, 'code' => $candidateCodes[0]], $catalogSize];
        }

        $finalCatalog = $this->buildVolcArkCatalogByCodes($candidateCodes);
        if ($finalCatalog === []) {
            return [['ok' => true, 'is_null' => true], $catalogSize];
        }

        $finalMatch = VolcArkVisionService::matchPhotoAutoWarehouse($tmp, $finalCatalog, $hintArg);
        if (!($finalMatch['ok'] ?? false)) {
            return [$finalMatch, $catalogSize];
        }
        $finalCode = trim((string) ($finalMatch['code'] ?? ''));
        if ($finalCode === '') {
            return [['ok' => true, 'is_null' => true], $catalogSize];
        }

        return [['ok' => true, 'code' => $finalCode], $catalogSize];
    }

    /**
     * @param iterable<\app\model\ProductStyleItem> $rows
     * @return list<array{code:string,desc:string,hot:string,thumb_url:string}>
     */
    private function buildVolcArkCatalogFromRows(iterable $rows): array
    {
        $prepared = [];
        $codes = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row->product_code ?? ''));
            if ($code === '') {
                continue;
            }
            $codes[] = $code;
            $prepared[] = [
                'code' => $code,
                'desc' => trim((string) ($row->ai_description ?? '')),
                'hot' => trim((string) ($row->hot_type ?? '')),
                'image_ref' => trim((string) ($row->image_ref ?? '')),
            ];
        }

        if ($prepared === []) {
            return [];
        }

        $productDescMap = [];
        $uniqueCodes = array_values(array_unique($codes));
        if ($uniqueCodes !== []) {
            $products = ProductModel::where('status', 1)->whereIn('name', $uniqueCodes)->select();
            foreach ($products as $product) {
                $name = trim((string) ($product->name ?? ''));
                if ($name === '' || isset($productDescMap[$name])) {
                    continue;
                }
                $productDescMap[$name] = trim((string) ($product->ai_description ?? ''));
            }
        }

        $catalog = [];
        foreach ($prepared as $row) {
            $desc = $row['desc'];
            if ($desc === '' && isset($productDescMap[$row['code']])) {
                $desc = $productDescMap[$row['code']];
            }
            $catalog[] = [
                'code' => $row['code'],
                'desc' => $desc !== '' ? $desc : ('catalog item: ' . $row['code']),
                'hot' => $row['hot'],
                'thumb_url' => $this->resolvePublicImageUrl($row['image_ref']),
            ];
        }

        return $catalog;
    }

    /**
     * @param list<string> $codes
     * @return list<array{code:string,desc:string,hot:string,thumb_url:string}>
     */
    private function buildVolcArkCatalogByCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(static fn ($v): string => trim((string) $v), $codes))));
        if ($codes === []) {
            return [];
        }

        $rows = ItemModel::where('status', 1)
            ->whereIn('product_code', $codes)
            ->order('id', 'desc')
            ->select();
        if ($rows->isEmpty()) {
            return [];
        }

        $latestByCode = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row->product_code ?? ''));
            if ($code === '' || isset($latestByCode[$code])) {
                continue;
            }
            $latestByCode[$code] = $row;
        }

        return $this->buildVolcArkCatalogFromRows(array_values($latestByCode));
    }

    private function resolvePublicImageUrl(string $imageRef): string
    {
        $ref = trim($imageRef);
        if ($ref === '' || str_starts_with($ref, '(')) {
            return '';
        }
        if (preg_match('#^https?://#i', $ref)) {
            return $ref;
        }
        $path = str_starts_with($ref, '/') ? $ref : '/' . $ref;
        $req = $this->request;

        return rtrim($req->domain(), '/') . $req->rootUrl() . $path;
    }

    /**
     * @param array{ok?:bool, error?:string, raw?:string} $match
     */
    private function tryKeywordFallbackOrError(string $hint, array $match, int $catalogSize)
    {
        if (mb_strlen($hint) >= 2) {
            $kw = ProductStyleKeywordSearchService::searchByHint($hint, 12);
            $items = $this->buildItemsFromKeywordRows($kw);
            if ($items !== []) {
                return $this->jsonOut([
                    'code' => 0,
                    'msg' => 'ok',
                    'data' => [
                        'items' => $items,
                        'engine' => 'volc_ark_keyword',
                        'catalog_size' => $catalogSize,
                        'fallback' => true,
                        'fallback_reason' => 'Visual matching did not return a valid code. Fallback to keyword search.',
                    ],
                ]);
            }
        }

        return $this->jsonOut([
            'code' => 1,
            'msg' => (string) ($match['error'] ?? 'Vision recognition failed. Please retry later.'),
            'data' => [
                'engine' => 'volc_ark',
                'catalog_size' => $catalogSize,
            ],
        ]);
    }

    private function tryKeywordFallbackOrNoMatch(string $hint, int $catalogSize, string $msg)
    {
        if (mb_strlen($hint) >= 2) {
            $kw = ProductStyleKeywordSearchService::searchByHint($hint, 12);
            $kwItems = $this->buildItemsFromKeywordRows($kw);
            if ($kwItems !== []) {
                return $this->jsonOut([
                    'code' => 0,
                    'msg' => 'ok',
                    'data' => [
                        'items' => $kwItems,
                        'engine' => 'volc_ark_keyword',
                        'catalog_size' => $catalogSize,
                        'fallback' => true,
                        'fallback_reason' => $msg . '; fallback to keyword search.',
                    ],
                ]);
            }
        }

        return $this->jsonOut([
            'code' => 1,
            'msg' => $msg,
            'data' => ['engine' => 'volc_ark', 'catalog_size' => $catalogSize],
        ]);
    }

    private function buildVolcArkItemForCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $styleRow = ItemModel::where('status', 1)->where('product_code', $code)->order('id', 'desc')->find();
        if (!$styleRow) {
            return null;
        }
        $product = ProductModel::where('name', $code)->where('status', 1)->find();
        if (!$product) {
            $product = ProductModel::whereLike('name', '%' . $code . '%')->where('status', 1)->order('id', 'desc')->find();
        }

        return [
            'product_code' => $code,
            'image_ref' => (string) ($styleRow->image_ref ?? ''),
            'hot_type' => (string) ($styleRow->hot_type ?? ''),
            'similarity' => 1.0,
            'match_reason' => 'Vision auto match',
            'product' => $product ? [
                'id' => (int) $product->id,
                'name' => (string) ($product->name ?? ''),
                'status' => (int) ($product->status ?? 0),
                'status_text' => ((int) ($product->status ?? 0)) === 1 ? 'active' : 'disabled',
                'goods_url' => (string) ($product->goods_url ?? ''),
            ] : null,
        ];
    }

    /**
     * @param iterable<\app\model\ProductStyleItem>|\think\Collection $rows
     * @return list<array<string, mixed>>
     */
    private function buildItemsFromKeywordRows($rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row->product_code ?? ''));
            if ($code === '') {
                continue;
            }
            $product = ProductModel::where('name', $code)->where('status', 1)->find();
            if (!$product) {
                $product = ProductModel::whereLike('name', '%' . $code . '%')->where('status', 1)->order('id', 'desc')->find();
            }
            $items[] = [
                'product_code' => $code,
                'image_ref' => (string) ($row->image_ref ?? ''),
                'hot_type' => (string) ($row->hot_type ?? ''),
                'similarity' => 0.35,
                'match_reason' => 'Keyword fallback match (no vision score)',
                'product' => $product ? [
                    'id' => (int) $product->id,
                    'name' => (string) ($product->name ?? ''),
                    'status' => (int) ($product->status ?? 0),
                    'status_text' => ((int) ($product->status ?? 0)) === 1 ? 'active' : 'disabled',
                    'goods_url' => (string) ($product->goods_url ?? ''),
                ] : null,
            ];
        }

        return $items;
    }
}
