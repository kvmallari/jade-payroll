<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Services\BIR1601CService;
use App\Services\BIR2316Service;
use App\Services\BIR2316TemplateService;
use App\Services\SSSReportService;
use App\Services\PhilHealthReportService;
use App\Services\PagibigReportService;
use App\Exports\BIR1601CExport;
use App\Exports\BIR2316Export;
use App\Exports\SSSReportExport;
use App\Exports\PhilHealthReportExport;
use App\Exports\PagibigReportExport;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class GovernmentFormsController extends Controller
{
    use AuthorizesRequests;

    protected $bir1601CService;
    protected $bir2316Service;
    protected $bir2316TemplateService;
    protected $sssReportService;
    protected $philHealthReportService;
    protected $pagibigReportService;

    public function __construct(
        BIR1601CService $bir1601CService,
        BIR2316Service $bir2316Service,
        BIR2316TemplateService $bir2316TemplateService,
        SSSReportService $sssReportService,
        PhilHealthReportService $philHealthReportService,
        PagibigReportService $pagibigReportService
    ) {
        $this->bir1601CService = $bir1601CService;
        $this->bir2316Service = $bir2316Service;
        $this->bir2316TemplateService = $bir2316TemplateService;
        $this->sssReportService = $sssReportService;
        $this->philHealthReportService = $philHealthReportService;
        $this->pagibigReportService = $pagibigReportService;
    }

    /**
     * Display government forms index page.
     */
    public function index()
    {
        $this->authorize('view reports');

        return view('government-forms.index');
    }

    /**
     * Show BIR 1601C form generator.
     */
    public function bir1601C(Request $request)
    {
        $this->authorize('generate reports');

        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $data = $this->bir1601CService->generateData($year, $month);

        if ($request->get('action') === 'download') {
            return $this->bir1601CService->downloadPDF($data, $year, $month);
        }

        if ($request->get('action') === 'excel') {
            $period = Carbon::create($year, $month)->format('F Y');
            return Excel::download(
                new BIR1601CExport($data['employees'], $data['summary'], $period),
                "bir-1601c-{$year}-{$month}.xlsx"
            );
        }

        return view('government-forms.bir-1601c', compact('data', 'year', 'month'));
    }

    /**
     * Show BIR 2316 form generator.
     */
    public function bir2316(Request $request)
    {
        $this->authorize('generate reports');

        $year = $request->get('year', now()->year);
        $employeeId = $request->get('employee_id');

        if ($employeeId) {
            $employee = Employee::findOrFail($employeeId);
            $data = $this->bir2316Service->generateForEmployee($employee, $year);

            if ($request->get('action') === 'download') {
                return $this->bir2316Service->downloadPDF($employee, $data, $year);
            }

            return view('government-forms.bir-2316-preview', compact('employee', 'data', 'year'));
        }

        $employees = Employee::active()->with(['user', 'department', 'position'])->get();

        if ($request->get('action') === 'download_all') {
            $allData = [];
            $employees = Employee::active()->get();
            foreach ($employees as $employee) {
                $employeeData = $this->bir2316Service->generateForEmployee($employee, $year);
                $allData[] = $employeeData;
            }
            return Excel::download(
                new BIR2316Export($allData, $year),
                "bir-2316-all-employees-{$year}.xlsx"
            );
        }

        if ($request->get('action') === 'excel' && $employeeId) {
            $employee = Employee::findOrFail($employeeId);
            $data = $this->bir2316Service->generateForEmployee($employee, $year);
            return Excel::download(
                new BIR2316Export([$data], $year),
                "bir-2316-{$employee->employee_id}-{$year}.xlsx"
            );
        }

        return view('government-forms.bir-2316', compact('employees', 'year'));
    }

    /**
     * Show SSS R-3 form generator.
     */
    public function sssR3(Request $request)
    {
        $this->authorize('generate reports');

        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $data = $this->sssReportService->generateR3Data($year, $month);

        if ($request->get('action') === 'download') {
            return $this->sssReportService->downloadPDF($data, $year, $month);
        }

        if ($request->get('action') === 'excel') {
            $period = Carbon::create($year, $month)->format('F Y');
            return Excel::download(
                new SSSReportExport($data['employees'], $data['summary'], $period),
                "sss-r3-{$year}-{$month}.xlsx"
            );
        }

        return view('government-forms.sss-r3', compact('data', 'year', 'month'));
    }

    /**
     * Show PhilHealth RF-1 form generator.
     */
    public function philHealthRF1(Request $request)
    {
        $this->authorize('generate reports');

        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $data = $this->philHealthReportService->generateRF1Data($year, $month);

        if ($request->get('action') === 'download') {
            return $this->philHealthReportService->downloadPDF($data, $year, $month);
        }

        if ($request->get('action') === 'excel') {
            $period = Carbon::create($year, $month)->format('F Y');
            return Excel::download(
                new PhilHealthReportExport($data['employees'], $data['summary'], $period),
                "philhealth-rf1-{$year}-{$month}.xlsx"
            );
        }

        return view('government-forms.philhealth-rf1', compact('data', 'year', 'month'));
    }

    /**
     * Show Pag-IBIG MCRF form generator.
     */
    public function pagibigMCRF(Request $request)
    {
        $this->authorize('generate reports');

        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $data = $this->pagibigReportService->generateMCRFData($year, $month);

        if ($request->get('action') === 'download') {
            return $this->pagibigReportService->downloadPDF($data, $year, $month);
        }

        if ($request->get('action') === 'excel') {
            $period = Carbon::create($year, $month)->format('F Y');
            return Excel::download(
                new PagibigReportExport($data['employees'], $data['summary'], $period),
                "pagibig-mcrf-{$year}-{$month}.xlsx"
            );
        }

        return view('government-forms.pagibig-mcrf', compact('data', 'year', 'month'));
    }

    /**
     * Show BIR 1604-C form generator (Annual).
     */
    public function bir1604C(Request $request)
    {
        $this->authorize('generate reports');

        $year = $request->get('year', now()->year - 1); // Previous year for annual report

        $data = $this->bir1601CService->generateAnnualData($year);

        if ($request->get('action') === 'download') {
            return $this->bir1601CService->downloadAnnualPDF($data, $year);
        }

        if ($request->get('action') === 'excel') {
            return $this->bir1601CService->downloadAnnualExcel($data, $year);
        }

        return view('government-forms.bir-1604c', compact('data', 'year'));
    }

    /**
     * Display list of active employees for BIR 2316 form generation
     */
    public function bir2316EmployeeList(Request $request)
    {
        $this->authorize('generate reports');

        $year = $request->get('year', now()->year);
        $perPage = $request->get('per_page', 10);

        $query = Employee::with(['user', 'department', 'position']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('department')) {
            $query->where('department_id', $request->department);
        }

        if ($request->filled('employment_status')) {
            $query->where('employment_status', $request->employment_status);
        } else {
            // Default to active employees only
            $query->where('employment_status', 'active');
        }

        // Apply sorting
        if ($request->filled('sort_name')) {
            if ($request->sort_name === 'asc') {
                $query->orderBy('first_name', 'asc')->orderBy('last_name', 'asc');
            } elseif ($request->sort_name === 'desc') {
                $query->orderBy('first_name', 'desc')->orderBy('last_name', 'desc');
            }
        } elseif ($request->filled('sort_hire_date')) {
            $query->orderBy('hire_date', $request->sort_hire_date);
        } else {
            // Default sorting by employee number
            $query->orderBy('employee_number');
        }

        // Paginate with query string preservation
        $employees = $query->paginate($perPage)->withQueryString();

        // Get departments for filter dropdown
        $departments = Department::active()->get();

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'employees' => $employees,
                'departments' => $departments,
                'year' => $year,
                'html' => view('government-forms.partials.bir-2316-employee-list', compact('employees', 'year'))->render()
            ]);
        }

        return view('government-forms.bir-2316-employees', compact('employees', 'year', 'departments'));
    }

    /**
     * Generate BIR 2316 summary (bulk download like payroll summary)
     */
    public function bir2316GenerateSummary(Request $request)
    {
        $this->authorize('generate reports');

        $year = $request->get('year', now()->year);
        $format = $request->get('export', 'pdf');

        // Get all active employees
        $employees = Employee::with(['user', 'department', 'position'])
            ->where('employment_status', 'active')
            ->orderBy('employee_number')
            ->get();

        if ($format === 'excel') {
            return $this->bir2316TemplateService->downloadAllExcel($employees, $year);
        } else {
            return $this->bir2316TemplateService->downloadAllPDF($employees, $year);
        }
    }
    /**
     * Generate individual BIR 2316 form for a specific employee
     */
    public function bir2316Individual(Request $request, Employee $employee)
    {
        // Check if user has proper authorization for government forms
        if (!$request->user()->hasAnyRole(['System Administrator', 'HR Head', 'HR Staff'])) {
            abort(403, 'Unauthorized access. Only System Administrator, HR Head, and HR Staff can access government forms.');
        }

        $year = $request->get('year', now()->year);

        // Generate the BIR 2316 data for this employee
        $data = $this->bir2316Service->generateForEmployee($employee, $year);

        return view('government-forms.bir-2316-individual', compact('employee', 'data', 'year'));
    }

    /**
     * Generate individual BIR 2316 download (like payroll summary)
     */
    public function bir2316IndividualGenerate(Request $request, Employee $employee)
    {
        // Check if user has proper authorization for government forms
        if (!$request->user()->hasAnyRole(['System Administrator', 'HR Head', 'HR Staff'])) {
            abort(403, 'Unauthorized access. Only System Administrator, HR Head, and HR Staff can access government forms.');
        }

        $year = $request->get('year', now()->year);
        $format = $request->get('export', 'pdf');

        if ($format === 'excel') {
            return $this->bir2316TemplateService->downloadIndividualExcel($employee, $year);
        } else {
            return $this->bir2316TemplateService->downloadIndividualPDF($employee, $year);
        }
    }

    /**
     * Generate individual BIR 2316 filled PDF download 
     */
    public function bir2316IndividualGenerateFilled(Request $request, Employee $employee)
    {
        // Check if user has proper authorization for government forms
        if (!$request->user()->hasAnyRole(['System Administrator', 'HR Head', 'HR Staff'])) {
            abort(403, 'Unauthorized access. Only System Administrator, HR Head, and HR Staff can access government forms.');
        }

        $year = $request->get('year', now()->year);

        return $this->bir2316TemplateService->downloadIndividualFilledPDF($employee, $year);
    }

    /**
     * Debug BIR 2316 PDF form filling
     */
    public function bir2316DebugPDF(Request $request, Employee $employee)
    {
        // Check if user has proper authorization for government forms
        if (!$request->user()->hasAnyRole(['System Administrator', 'HR Head', 'HR Staff'])) {
            abort(403, 'Unauthorized access. Only System Administrator, HR Head, and HR Staff can access government forms.');
        }

        $year = $request->get('year', now()->year);

        $debugResult = $this->bir2316TemplateService->testPDFFormFilling($employee, $year);

        return response()->json($debugResult, 200, [], JSON_PRETTY_PRINT);
    }
}
