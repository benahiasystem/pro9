<?php
// ######## INICIO MODALIDAD DE EMISIÓN FISCAL ########

namespace Modules\Dashboard\Http\Controllers;

use App\Exports\AccountsReceivable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Dashboard\Helpers\DashboardData;
use Modules\Dashboard\Helpers\DashboardKpi;
use Modules\Dashboard\Helpers\DashboardUtility;
use Modules\Dashboard\Helpers\DashboardSalePurchase;
use Modules\Dashboard\Helpers\DashboardView;
use Modules\Dashboard\Helpers\DashboardStock;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\Document;
use App\Models\Tenant\Company;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Arr;
use Modules\Dashboard\Helpers\DashboardInventory;
use App\Models\Tenant\Configuration;
use Modules\Dashboard\Widgets\WidgetSourceRegistry;
use Modules\Dashboard\Models\DashboardLayout;

/**
 * Class DashboardController
 *
 * @package Modules\Dashboard\Http\Controllers
 * @mixin Controller
 */
class DashboardController extends Controller
{
    public function index()
    {
        // dd('aqui');
        if(auth()->user()->type != 'admin' && !auth()->user()->searchModule('dashboard')){
            return redirect()->route('tenant.documents.index');
        } elseif (auth()->user()->type == 'admin' && !auth()->user()->searchModule('dashboard')) {
            return redirect()->route('tenant.documents.index');
        }

        $company = Company::select('fiscal_environment')->first();
        $company_environment  = $company->fiscal_environment;
        $configuration = Configuration::first();

        return view('dashboard::index', compact('company_environment','configuration'));
    }

    public function filter()
    {
        return [
            'establishments' => DashboardView::getEstablishments(),
            'establishment_id' => auth()->user()->establishment_id,
        ];
    }

    public function globalData(Request $request)
    {
        return response()->json((new DashboardData())->globalData($request->all()), 200);
    }

    public function cashFlow(Request $request)
    {
        return response()->json((new DashboardData())->cashFlow($request->all()), 200);
    }

    public function lowStock(Request $request)
    {
        return response()->json((new DashboardData())->lowStock($request->all()), 200);
    }

    public function salesWeek(Request $request)
    {
        return response()->json((new DashboardData())->salesWeek($request->all()), 200);
    }

    public function paymentMethods(Request $request)
    {
        return response()->json((new DashboardData())->paymentMethods($request->all()), 200);
    }

    public function sunatStatus(Request $request)
    {
        return response()->json((new DashboardData())->sunatStatus($request->all()), 200);
    }

    public function debtors(Request $request)
    {
        return response()->json((new DashboardData())->debtors($request->all()), 200);
    }

    public function monthGoal()
    {
        return response()->json((new DashboardData())->monthGoal(), 200);
    }

    public function data(Request $request)
    {
        return [
            'data' => (new DashboardData())->data($request->all()),
        ];
    }

    public function kpi(Request $request)
    {
        return [
            'data' => (new DashboardKpi())->data($request->all()),
        ];
    }

    public function monthlyComparison(Request $request)
    {
        return [
            'data' => (new DashboardKpi())->monthlyComparison($request->all()),
        ];
    }

    public function salesGrowth(Request $request)
    {
        return [
            'data' => (new DashboardKpi())->salesGrowth($request->all()),
        ];
    }

    // public function unpaid(Request $request)
    // {
    //     return [
    //             'records' => (new DashboardView())->getUnpaid($request->all())
    //     ];
    // }

    // public function unpaidall()
    // {

    //     return Excel::download(new AccountsReceivable, 'Allclients.xlsx');

    // }

    public function data_aditional(Request $request)
    {
        return [
            'data' => (new DashboardSalePurchase())->data($request->all()),
        ];
    }

    public function igvSales(Request $request)
    {
        return [
            'data' => (new DashboardData())->salesTotalByRange(
                $request->input('establishment_id'),
                $request->input('date_start'),
                $request->input('date_end')
            ),
        ];
    }

    public function igvPurchases(Request $request)
    {
        return [
            'data' => (new DashboardSalePurchase())->purchasesTotalByRange(
                $request->input('establishment_id'),
                $request->input('date_start'),
                $request->input('date_end')
            ),
        ];
    }

    public function stockByProduct(Request $request)
    {
        return  (new DashboardStock())->data($request);
    }


    public function utilities(Request $request)
    {
        return [
            'data' => (new DashboardUtility())->data($request->all()),
        ];
    }

    public function df()
    {
        $path = app_path();
        //df -m -h --output=used,avail,pcent /

        $used = new Process(['df' ,'-m', '-h', '--output=used','/']);
        $used->run();
        if (!$used->isSuccessful()) {
            return ['error'];
            throw new ProcessFailedException($used);
        }
        $disc_used = $used->getOutput();
        $array[] = str_replace("\n","",$disc_used);

        $avail = new Process(['df', '-m', '-h', '--output=avail', '/']);
        $avail->run();
        if (!$avail->isSuccessful()) {
            return ['error'];
            throw new ProcessFailedException($avail);
        }
        $disc_avail = $avail->getOutput();
        $array[] = str_replace("\n","",$disc_avail);

        $pcent = new Process(['df' ,'-m' ,'-h' , '--output=pcent' ,'/']);
        $pcent->run();
        if (!$pcent->isSuccessful()) {
            return ['error'];
            throw new ProcessFailedException($pcent);
        }
        $disc_pcent = $pcent->getOutput();
        $array[] = str_replace("\n","",$disc_pcent);

        return $array;


    }

    /**
     * Extensión de ventas por producto
     *
     */
    public function salesByProduct()
    {
        return view('dashboard::sales_by_product');
    }

    public function productOfDue(Request $request)
    {
        return  (new DashboardInventory())->data($request);
    }

    public function widgetCatalog()
    {
        return response()->json(app(WidgetSourceRegistry::class)->catalog(), 200);
    }

    public function widgetLayout()
    {
        $record = DashboardLayout::where('user_id', auth()->id())->first();

        return response()->json([
            'layout' => $record ? $record->layout : null,
        ], 200);
    }

    /**
     * Guarda el layout del usuario validado contra el catálogo: widgets de
     * fuentes o tipos inexistentes se descartan (equivalente al loadLayout
     * defensivo del frontend).
     */
    public function widgetLayoutStore(Request $request)
    {
        $registry = app(WidgetSourceRegistry::class);

        $layout = collect((array) $request->input('layout', []))
            ->filter(function ($widget) use ($registry) {
                return is_array($widget)
                    && !empty($widget['id'])
                    && !empty($widget['source'])
                    && !empty($widget['type'])
                    && $registry->has($widget['source']);
            })
            ->map(function ($widget) {
                return [
                    'id' => (string) $widget['id'],
                    'source' => (string) $widget['source'],
                    'type' => (string) $widget['type'],
                    'size' => (string) ($widget['size'] ?? 'm'),
                    'cols' => isset($widget['cols']) ? (int) $widget['cols'] : null,
                    'rows' => isset($widget['rows']) ? (int) $widget['rows'] : null,
                    'options' => (array) ($widget['options'] ?? []),
                ];
            })
            ->values()
            ->all();

        DashboardLayout::updateOrCreate(
            ['user_id' => auth()->id()],
            ['layout' => $layout]
        );

        return response()->json(['success' => true, 'layout' => $layout], 200);
    }

    /**
     * Resuelve datos de varios widgets en una sola petición.
     * Body: { widgets: [{ source, options? }, ...], filters: {...} }
     */
    public function widgetData(Request $request)
    {
        $widgets = (array) $request->input('widgets', []);
        $filters = (array) $request->input('filters', []);

        return response()->json([
            'data' => app(WidgetSourceRegistry::class)->resolveBatch($widgets, $filters),
        ], 200);
    }

}
// ######## FIN MODALIDAD DE EMISIÓN FISCAL ########
