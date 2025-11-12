<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollSnapshot;
use App\Models\EmployerSetting;
use App\Models\BIR2316Setting;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use mikehaertl\pdftk\Pdf;

class BIR2316TemplateService
{
    private $templatePath;
    private $pdfTemplatePath;

    public function __construct()
    {
        $this->templatePath = storage_path('app/private/BIR-2316.xltx');
        $this->pdfTemplatePath = storage_path('app/private/BIR-23161.pdf');
    }

    /**
     * Generate BIR 2316 data for a specific employee and year.
     */
    public function generateEmployeeData(Employee $employee, $year)
    {
        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = $startDate->copy()->endOfYear();

        // Get all payroll snapshots for the employee for the year
        $payrollSnapshots = PayrollSnapshot::where('employee_id', $employee->id)
            ->whereHas('payroll', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('period_start', [$startDate, $endDate]);
            })
            ->with('payroll')
            ->get();

        // Get employer settings for employer information
        $employerSettings = EmployerSetting::getSettings();

        $totals = [
            'gross_compensation' => 0,
            'basic_salary' => 0,
            'overtime_pay' => 0,
            'night_differential' => 0,
            'holiday_pay' => 0,
            'allowances' => 0,
            'bonuses' => 0,
            'incentives' => 0,
            'other_compensation' => 0,
            'sss_contribution' => 0,
            'philhealth_contribution' => 0,
            'pagibig_contribution' => 0,
            'union_dues' => 0,
            'total_contributions' => 0,
            'tax_withheld' => 0,
            'non_taxable_13th_month' => 0,
            'non_taxable_de_minimis' => 0,
            'taxable_compensation' => 0,
            'other_deductions' => 0,
        ];

        // Calculate totals from payroll snapshots
        foreach ($payrollSnapshots as $snapshot) {
            $totals['basic_salary'] += $snapshot->regular_pay ?? 0;
            $totals['overtime_pay'] += $snapshot->overtime_pay ?? 0;
            $totals['night_differential'] += $snapshot->night_differential_pay ?? 0;
            $totals['holiday_pay'] += $snapshot->holiday_pay ?? 0;
            $totals['allowances'] += $snapshot->allowances_total ?? 0;
            $totals['bonuses'] += $snapshot->bonuses_total ?? 0;
            $totals['incentives'] += $snapshot->incentives_total ?? 0;
            $totals['other_compensation'] += $snapshot->other_earnings ?? 0;
            $totals['sss_contribution'] += $snapshot->sss_contribution ?? 0;
            $totals['philhealth_contribution'] += $snapshot->philhealth_contribution ?? 0;
            $totals['pagibig_contribution'] += $snapshot->pagibig_contribution ?? 0;
            $totals['tax_withheld'] += $snapshot->withholding_tax ?? 0;
            $totals['other_deductions'] += $snapshot->other_deductions ?? 0;
        }

        // Calculate derived values
        $totals['gross_compensation'] = $totals['basic_salary'] + $totals['overtime_pay'] +
            $totals['night_differential'] + $totals['holiday_pay'] +
            $totals['allowances'] + $totals['bonuses'] + $totals['incentives'] +
            $totals['other_compensation'];

        $totals['total_contributions'] = $totals['sss_contribution'] +
            $totals['philhealth_contribution'] + $totals['pagibig_contribution'] +
            $totals['union_dues'];

        // Calculate 13th month (non-taxable portion up to 90,000)
        $thirteenthMonth = $totals['basic_salary']; // Simplified calculation
        $totals['non_taxable_13th_month'] = min($thirteenthMonth, 90000);

        $totals['taxable_compensation'] = $totals['gross_compensation'] - $totals['total_contributions'] -
            $totals['non_taxable_13th_month'] - $totals['non_taxable_de_minimis'];

        // Add employer and employee information
        $totals['employer'] = [
            'name' => $employerSettings->registered_business_name ?? 'N/A',
            'tin' => $employerSettings->tax_identification_number ?? 'N/A',
            'address' => $employerSettings->registered_address ?? 'N/A',
            'zip_code' => $employerSettings->postal_zip_code ?? 'N/A',
            'rdo_code' => $employerSettings->rdo_code ?? 'N/A',
            'signatory_name' => $employerSettings->signatory_name ?? 'N/A',
        ];

        $totals['employee'] = [
            'name' => trim($employee->first_name . ' ' . ($employee->middle_name ?? '') . ' ' . $employee->last_name),
            'tin' => $employee->tin_number ?? 'N/A',
            'address' => $employee->address ?? 'N/A',
        ];

        return $totals;
    }

    /**
     * Inject data into the Excel template for a single employee.
     */
    public function injectDataToTemplate(Employee $employee, $data, $year)
    {
        try {
            // Load the template
            $spreadsheet = IOFactory::load($this->templatePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Year input (Part 1)
            $worksheet->setCellValue('D12', $year);

            // Employee Information (Part I)
            $worksheet->setCellValue('D14', $employee->tin_number ?? 'Not provided'); // Employee TIN
            $worksheet->setCellValue('D16', $employee->first_name . ' ' . ($employee->middle_name ? $employee->middle_name . ' ' : '') . $employee->last_name); // Employee Name
            $worksheet->setCellValue('D19', $employee->address ?? 'Not provided'); // Employee Address

            // Employer Information (Part II)
            $worksheet->setCellValue('D40', $data['employer']['name'] ?? 'Your Company Name'); // Employer Name
            $worksheet->setCellValue('D43', $data['employer']['address'] ?? 'Your Company Address'); // Employer Address
            $worksheet->setCellValue('D49', $data['employer']['tin'] ?? 'XXX-XXX-XXX-XXX'); // Employer TIN
            $worksheet->setCellValue('H49', $data['employer']['rdo_code'] ?? 'XXX'); // RDO Code

            // Compensation Information (Part IV Summary)
            $worksheet->setCellValue('K58', number_format($data['gross_compensation'], 2, '.', '')); // Gross Compensation Income
            $worksheet->setCellValue('K60', number_format($data['non_taxable_13th_month'] + $data['non_taxable_de_minimis'], 2, '.', '')); // Total Non-Taxable
            $worksheet->setCellValue('K62', number_format($data['taxable_compensation'], 2, '.', '')); // Taxable Compensation from Present
            $worksheet->setCellValue('K66', number_format($data['taxable_compensation'], 2, '.', '')); // Gross Taxable Compensation (same as K62 for single employer)
            $worksheet->setCellValue('K70', number_format($data['tax_withheld'], 2, '.', '')); // Amount of Taxes Withheld
            $worksheet->setCellValue('K80', number_format($data['tax_withheld'], 2, '.', '')); // Total Taxes Withheld Final

            return $spreadsheet;
        } catch (\Exception $e) {
            // Log the error and return a basic spreadsheet
            Log::error('Error injecting data to BIR template: ' . $e->getMessage());
            throw new \Exception('Unable to process BIR template: ' . $e->getMessage());
        }
    }

    /**
     * Download individual Excel file for an employee (.xltx format).
     */
    public function downloadIndividualExcel(Employee $employee, $year)
    {
        // Use the exact Excel template file: BIR-2316.xltx
        if (file_exists($this->templatePath)) {
            // Create filename with employee name format: BIR_2316_LASTNAME_FIRSTNAME_YEAR
            $lastName = strtoupper($employee->last_name);
            $firstName = strtoupper($employee->first_name);
            $middleName = $employee->middle_name ? strtoupper($employee->middle_name) : '';

            $fullName = $middleName ? "{$lastName}_{$firstName}_{$middleName}" : "{$lastName}_{$firstName}";
            $filename = "BIR_2316_{$fullName}_{$year}.xltx";
            $tempPath = storage_path('app/temp/' . $filename);

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            // Copy the exact Excel template
            copy($this->templatePath, $tempPath);

            return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
        } else {
            throw new \Exception('Excel template file BIR-2316.xltx not found in storage/app/private/');
        }
    }
    /**
     * Download individual PDF file for an employee.
     */
    public function downloadIndividualPDF(Employee $employee, $year)
    {
        // Use the exact PDF template file: BIR-23161.pdf
        if (file_exists($this->pdfTemplatePath)) {
            // Create filename with employee name format: BIR_2316_LASTNAME_FIRSTNAME_YEAR
            $lastName = strtoupper($employee->last_name);
            $firstName = strtoupper($employee->first_name);
            $middleName = $employee->middle_name ? strtoupper($employee->middle_name) : '';

            $fullName = $middleName ? "{$lastName}_{$firstName}_{$middleName}" : "{$lastName}_{$firstName}";
            $filename = "BIR_2316_{$fullName}_{$year}.pdf";
            $tempPath = storage_path('app/temp/' . $filename);

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            // Copy the exact PDF template
            copy($this->pdfTemplatePath, $tempPath);

            return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
        } else {
            throw new \Exception('PDF template file BIR-23161.pdf not found in storage/app/private/');
        }
    }

    /**
     * Download individual filled PDF file for an employee using pdftk.
     */
    public function downloadIndividualFilledPDF(Employee $employee, $year)
    {
        // Use the exact PDF template file: BIR-23161.pdf
        if (!file_exists($this->pdfTemplatePath)) {
            throw new \Exception('PDF template file BIR-23161.pdf not found in storage/app/private/');
        }

        try {
            // Generate employee data for the year
            $data = $this->generateEmployeeData($employee, $year);

            // Create filename with employee name format: BIR_2316_FILLED_LASTNAME_FIRSTNAME_YEAR
            $lastName = strtoupper($employee->last_name);
            $firstName = strtoupper($employee->first_name);
            $middleName = $employee->middle_name ? strtoupper($employee->middle_name) : '';

            $fullName = $middleName ? "{$lastName}_{$firstName}_{$middleName}" : "{$lastName}_{$firstName}";
            $filename = "BIR_2316_FILLED_{$fullName}_{$year}.pdf";

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $outputPath = storage_path('app/temp/' . $filename);

            // Prepare field data for PDF form filling
            $formData = $this->preparePDFFormData($employee, $data, $year);

            // Log form data for debugging
            Log::info('BIR PDF Form Data for ' . $employee->employee_number, [
                'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                'form_data_count' => count($formData),
                'sample_data' => array_slice($formData, 0, 10, true), // Log first 10 fields
                'gross_compensation' => $data['gross_compensation'] ?? 'N/A',
                'tax_withheld' => $data['tax_withheld'] ?? 'N/A'
            ]);

            // Try to replicate exactly what works in your standalone script
            // Use the same approach: basic fields with simple data

            // Parse TIN for basic test (max 14 characters)
            $tinParts = [];
            if ($employee->tin_number) {
                $tin = substr(preg_replace('/[^0-9A-Za-z]/', '', $employee->tin_number), 0, 14);
                if (strlen($tin) >= 9) {
                    $tinParts = [
                        substr($tin, 0, 3),  // First 3 characters
                        substr($tin, 3, 3),  // Next 3 characters  
                        substr($tin, 6, 3),  // Next 3 characters
                        substr($tin, 9)      // Remaining characters (up to 5)
                    ];
                }
            }

            // Parse employer TIN for basic test (max 14 characters)
            $employerTinParts = [];
            if (isset($data['employer']['tin'])) {
                $employerTin = substr(preg_replace('/[^0-9A-Za-z]/', '', $data['employer']['tin']), 0, 14);
                if (strlen($employerTin) >= 9) {
                    $employerTinParts = [
                        substr($employerTin, 0, 3),  // First 3 characters
                        substr($employerTin, 3, 3),  // Next 3 characters  
                        substr($employerTin, 6, 3),  // Next 3 characters
                        substr($employerTin, 9)      // Remaining characters (up to 5)
                    ];
                }
            }

            // Use the complete form data that includes BIR 2316 settings
            Log::info('Using complete form data with BIR 2316 settings', ['form_data_count' => count($formData)]);

            // Skip the library entirely and use direct PDFtk execution
            // This replicates exactly what works in your standalone script
            $result = false;

            try {
                // Create a simple FDF file manually (like your working script) using complete form data
                $fdfContent = $this->createSimpleFDF($formData);
                $fdfPath = storage_path('app/temp/simple_form_data.fdf');

                // Ensure temp directory exists
                if (!file_exists(dirname($fdfPath))) {
                    mkdir(dirname($fdfPath), 0755, true);
                }

                file_put_contents($fdfPath, $fdfContent);

                // Use the exact same command structure as your working script
                // Use full path to PDFtk since PHP exec doesn't have same PATH as command line
                $pdftk = 'C:\Program Files (x86)\PDFtk\bin\pdftk.exe';
                $command = sprintf(
                    '"%s" "%s" fill_form "%s" output "%s"',
                    $pdftk,
                    $this->pdfTemplatePath,
                    $fdfPath,
                    $outputPath
                );

                Log::info('Executing standalone-style PDFtk command', [
                    'command' => $command,
                    'template_exists' => file_exists($this->pdfTemplatePath),
                    'fdf_exists' => file_exists($fdfPath),
                    'fdf_size' => filesize($fdfPath)
                ]);

                // Execute the command
                $output = [];
                $returnCode = 0;
                exec($command . ' 2>&1', $output, $returnCode);

                Log::info('Standalone PDFtk execution result', [
                    'return_code' => $returnCode,
                    'command_output' => $output,
                    'output_file_created' => file_exists($outputPath),
                    'output_file_size' => file_exists($outputPath) ? filesize($outputPath) : 0
                ]);

                // Clean up FDF file
                if (file_exists($fdfPath)) {
                    unlink($fdfPath);
                }

                if ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
                    Log::info('Standalone PDFtk method succeeded!');
                    $result = true;
                } else {
                    Log::error('Standalone PDFtk method failed', [
                        'exit_code' => $returnCode,
                        'output' => implode(' ', $output)
                    ]);
                    $result = false;
                }
            } catch (\Exception $e) {
                Log::error('Standalone method exception: ' . $e->getMessage());
                $result = false;
            }

            if (!$result) {
                Log::error('PDFtk library approach failed for employee: ' . $employee->employee_number, [
                    'form_data_count' => count($formData),
                    'template_exists' => file_exists($this->pdfTemplatePath),
                    'template_size' => file_exists($this->pdfTemplatePath) ? filesize($this->pdfTemplatePath) : 0
                ]);
                // Final fallback to empty template
                copy($this->pdfTemplatePath, $outputPath);
                Log::info('Using empty template as fallback');
            } else {
                Log::info('PDF filling succeeded for employee: ' . $employee->employee_number, [
                    'form_data_fields' => count($formData),
                    'output_size' => filesize($outputPath)
                ]);
            }

            return response()->download($outputPath, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error generating filled PDF: ' . $e->getMessage(), [
                'employee' => $employee->employee_number ?? 'unknown',
                'year' => $year,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Unable to generate filled PDF: ' . $e->getMessage());
        }
    }

    /**
     * Get PDF form field names from the BIR template
     */
    public function getPDFFormFields()
    {
        try {
            $pdf = new Pdf($this->pdfTemplatePath);
            $fields = $pdf->getDataFields();
            return $fields;
        } catch (\Exception $e) {
            Log::error('Error getting PDF form fields: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Debug method to test PDF form filling with simple data
     */
    public function testPDFFormFilling($employee, $year)
    {
        if (!file_exists($this->pdfTemplatePath)) {
            return ['error' => 'PDF template not found'];
        }

        try {
            // Get form fields first
            $fields = $this->getPDFFormFields();
            $fieldNames = [];
            if ($fields && is_array($fields)) {
                foreach ($fields as $field) {
                    if (isset($field['FieldName'])) {
                        $fieldNames[] = $field['FieldName'];
                    }
                }
            }

            // Generate test data
            $data = $this->generateEmployeeData($employee, $year);
            $formData = $this->preparePDFFormData($employee, $data, $year);

            // Create test output
            $testOutputPath = storage_path('app/temp/test_debug_fill.pdf');

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            // Calculate dynamic MWE for test data
            $birSettings = BIR2316Setting::where('tax_year', $year)->first();
            $employeeDailyRate = $this->calculateEmployeeDailyRate($employee);
            $statutoryMinWage = $birSettings ? $birSettings->statutory_minimum_wage_per_day : 0;

            $mweValue = ($statutoryMinWage > 0 && $employeeDailyRate <= $statutoryMinWage) ? 'Yes' : 'No';

            // Try simple fill first
            $simpleFillData = [
                'year' => (string)$year,
                'employee_name' => 'TEST EMPLOYEE NAME',
                '19' => '100000.00',
                '21' => '90000.00',
                '28' => '10000.00',
                'mwe' => $mweValue
            ];

            $pdf = new Pdf($this->pdfTemplatePath);
            $result = $pdf->fillForm($simpleFillData)->saveAs($testOutputPath);

            return [
                'success' => $result,
                'error' => $result ? null : $pdf->getError(),
                'available_fields_count' => count($fieldNames),
                'sample_fields' => array_slice($fieldNames, 0, 20),
                'form_data_fields' => array_keys($formData),
                'matching_fields' => array_intersect($fieldNames, array_keys($formData)),
                'test_file_created' => file_exists($testOutputPath),
                'test_file_size' => file_exists($testOutputPath) ? filesize($testOutputPath) : 0,
                'employee_data_sample' => [
                    'gross_compensation' => $data['gross_compensation'] ?? 'N/A',
                    'basic_salary' => $data['basic_salary'] ?? 'N/A',
                    'tax_withheld' => $data['tax_withheld'] ?? 'N/A'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }

    /**
     * Prepare form field data for PDF filling using actual BIR form field names
     */
    private function preparePDFFormData(Employee $employee, $data, $year)
    {
        // Retrieve BIR 2316 settings for the tax year
        $birSettings = BIR2316Setting::where('tax_year', $year)->first();

        // Parse TIN number for individual field mapping (max 14 characters)
        $tinParts = [];
        if ($employee->tin_number) {
            // Keep only alphanumeric characters, max 14 chars
            $tin = substr(preg_replace('/[^0-9A-Za-z]/', '', $employee->tin_number), 0, 14);

            if (strlen($tin) >= 9) {
                $tinParts = [
                    substr($tin, 0, 3),  // First 3 characters
                    substr($tin, 3, 3),  // Next 3 characters  
                    substr($tin, 6, 3),  // Next 3 characters
                    substr($tin, 9)      // Remaining characters (up to 5)
                ];
            }
        }

        // Parse employer TIN for individual field mapping (max 14 characters)
        $employerTinParts = [];
        if (isset($data['employer']['tin'])) {
            // Keep only alphanumeric characters, max 14 chars (same as employee TIN format)
            $employerTin = substr(preg_replace('/[^0-9A-Za-z]/', '', $data['employer']['tin']), 0, 14);
            if (strlen($employerTin) >= 9) {
                $employerTinParts = [
                    substr($employerTin, 0, 3),  // First 3 characters
                    substr($employerTin, 3, 3),  // Next 3 characters  
                    substr($employerTin, 6, 3),  // Next 3 characters
                    substr($employerTin, 9)      // Remaining characters (up to 5)
                ];
            }
        }

        $formData = [
            // Year field - ensure max 4 characters
            'year' => substr((string)$year, -4),

            // Employee TIN (broken down into parts as per PDF form structure)
            'employee_tin_1' => $tinParts[0] ?? '',
            'employee_tin_2' => $tinParts[1] ?? '',
            'employee_tin_3' => $tinParts[2] ?? '',
            'employee_tin_4' => $tinParts[3] ?? '',

            // Employee Information - Format: "Last Name, First Name, Middle Name"
            'employee_name' => $this->formatEmployeeName($employee),

            // Employee fields from employees table
            'employee_local_address' => $employee->address ?? '', // 'address' column
            'employee_birthday' => $employee->birth_date ? Carbon::parse($employee->birth_date)->format('mdY') : '', // 'birth_date' column - no slashes: 05232003
            'employee_contactnumber' => $employee->phone ?? '', // 'phone' column
            'employee_id' => $employee->employee_number ?? '',

            // Employee signature name - different format: 'FIRST MIDDLE LAST'
            'employee_name_signature' => $this->formatEmployeeNameSignature($employee), // Field 54 and 56 format

            'employee_local_address_zip' => $employee->postal_code ?? '',

            // Employer fields from employers_settings table
            'rdo' => $data['employer']['rdo_code'] ?? '', // 'rdo_code' column

            // Present Employer TIN from employers_settings.tax_identification_number
            'employer_present_tin_1' => $employerTinParts[0] ?? '',
            'employer_present_tin_2' => $employerTinParts[1] ?? '',
            'employer_present_tin_3' => $employerTinParts[2] ?? '',
            'employer_present_tin_4' => $employerTinParts[3] ?? '',

            // Employer information from employers_settings table
            'employer_name_present' => $data['employer']['name'] ?? '', // 'registered_business_name' column
            'employer_registered_address_present' => $data['employer']['address'] ?? '', // 'registered_address' column
            'employer_registered_address_zip_present' => $data['employer']['zip_code'] ?? '', // 'postal_zip_code' column

            // Authorized signature from employers_settings table
            'authorized_signature' => $data['employer']['signatory_name'] ?? '', // 'signatory_name' column

            // Main employer checkbox - use 'Yes' for checkbox fields
            'main_employer' => 'Yes',

            // Periods (using string format)
            'period_from' => (string)$year,
            'period_to' => (string)$year,

            // // Compensation amounts (using the numbered fields from BIR form)
            // // Remove number_format to avoid any formatting issues, use raw numbers with 2 decimal places
            // '19' => sprintf('%.2f', $data['gross_compensation']), // Gross Compensation Income from Present
            // '20' => sprintf('%.2f', $data['non_taxable_13th_month'] + ($data['non_taxable_de_minimis'] ?? 0)), // Non-taxable income
            // '21' => sprintf('%.2f', $data['taxable_compensation']), // Taxable Compensation Income from Present
            // '23' => sprintf('%.2f', $data['taxable_compensation']), // Gross Taxable Compensation Income
            // '24' => sprintf('%.2f', $data['tax_withheld']), // Tax Withheld from Present Employer
            // '26' => sprintf('%.2f', $data['tax_withheld']), // Total Amount of Taxes Withheld
            // '28' => sprintf('%.2f', $data['tax_withheld']), // Total Taxes Withheld

            // // Detailed compensation breakdown
            // '29' => sprintf('%.2f', $data['basic_salary']), // Basic Salary

            // // Mandatory contributions
            // '36' => sprintf('%.2f', $data['total_contributions']), // SSS, GSIS, PHIC & PAG-IBIG Contributions
            // '37' => sprintf('%.2f', $data['gross_compensation']), // Salaries and Other Forms of Compensation
            // '38' => sprintf('%.2f', $data['non_taxable_13th_month'] + ($data['non_taxable_de_minimis'] ?? 0)), // Total Non-Taxable/Exempt

            // Date fields and BIR Settings integration
            'date_issued' => $birSettings && $birSettings->date_issued
                ? Carbon::parse($birSettings->date_issued)->format('mdY')
                : now()->format('mdY'),
        ];

        // Calculate additional fields from payroll snapshots for the same tax year
        $additionalData = $this->calculateAdditionalFieldsFromPayrollSnapshots($employee, $year);

        // Add compensation fields with proper rounding (no decimals, round .50+ up, .49- down)
        // Always populate these fields for automatic calculations (even if 0)
        $formData['29'] = (string)round($additionalData['basic_salary']); // Basic Salary
        $formData['30'] = (string)round($additionalData['holiday_pay']); // Holiday Pay
        $formData['31'] = (string)round($additionalData['overtime_pay']); // Overtime Pay
        $formData['32'] = (string)round($additionalData['night_differential']); // Night Shift Differential
        $formData['34'] = (string)round($additionalData['bonuses_total']); // 13th Month Pay and Other Benefits
        $formData['35'] = (string)round($additionalData['allowances_total']); // De Minimis Benefits
        $formData['36'] = (string)round($additionalData['total_deductions']); // SSS, GSIS, PHIC & PAG-IBIG Contributions

        // // Add mandatory contributions and other required fields
        // $formData['36'] = sprintf('%.2f', $data['total_contributions']); // SSS, GSIS, PHIC & PAG-IBIG Contributions
        // $formData['37'] = sprintf('%.2f', $data['gross_compensation']); // Salaries and Other Forms of Compensation
        // $formData['38'] = sprintf('%.2f', $data['non_taxable_13th_month'] + ($data['non_taxable_de_minimis'] ?? 0)); // Total Non-Taxable/Exempt

        // Date fields and BIR Settings integration
        $formData['date_issued'] = $birSettings && $birSettings->date_issued
            ? Carbon::parse($birSettings->date_issued)->format('mdY')
            : now()->format('mdY');

        // Add BIR 2316 settings fields if available
        if ($birSettings) {
            // Employee rate per day from statutory minimum wage - no decimal places
            if ($birSettings->statutory_minimum_wage_per_day) {
                $formData['employee_rateperday'] = number_format($birSettings->statutory_minimum_wage_per_day, 0, '', '');
            }

            // Employee rate per month from statutory minimum wage - no decimal places
            if ($birSettings->statutory_minimum_wage_per_month) {
                $formData['employee_ratepermonth'] = number_format($birSettings->statutory_minimum_wage_per_month, 0, '', '');
            }

            // Period from and to - remove hyphens from MM-DD format
            if ($birSettings->period_from) {
                $formData['period_from'] = str_replace('-', '', $birSettings->period_from);
            }
            if ($birSettings->period_to) {
                $formData['period_to'] = str_replace('-', '', $birSettings->period_to);
            }

            // Place of issue
            if ($birSettings->place_of_issue) {
                $formData['place_of_issue'] = $birSettings->place_of_issue;
            }

            // Amount paid CTC
            if ($birSettings->amount_paid_ctc) {
                $formData['amount_paid_ctc'] = sprintf('%.2f', $birSettings->amount_paid_ctc);
            }

            // Authorized person signature date - format from Y-m-d to mmddyyyy
            if ($birSettings->date_signed_by_authorized_person) {
                $formData['authorized_signature_date_signed'] = Carbon::parse($birSettings->date_signed_by_authorized_person)->format('mdY');
            }

            // Employee signature date - format from Y-m-d to mmddyyyy  
            if ($birSettings->date_signed_by_employee) {
                $formData['employee_signature_date_signed'] = Carbon::parse($birSettings->date_signed_by_employee)->format('mdY');
            }
        }

        // Calculate MWE (Minimum Wage Earner) status
        $employeeDailyRate = $this->calculateEmployeeDailyRate($employee);
        $statutoryMinWage = $birSettings ? $birSettings->statutory_minimum_wage_per_day : 0;

        // Set MWE field based on comparison
        if ($statutoryMinWage > 0 && $employeeDailyRate <= $statutoryMinWage) {
            $formData['mwe'] = 'Yes';
        } else {
            $formData['mwe'] = 'No';
        }

        // Automatic field calculations
        $this->calculateAutomaticFields($formData);

        return $formData;
    }

    /**
     * Format employee name as "Last Name, First Name, Middle Name"
     */
    private function formatEmployeeName(Employee $employee)
    {
        $lastName = trim($employee->last_name ?? '');
        $firstName = trim($employee->first_name ?? '');
        $middleName = trim($employee->middle_name ?? '');

        // Start with last name, first name
        $formattedName = $lastName . ', ' . $firstName;

        // Add middle name if available
        if (!empty($middleName)) {
            $formattedName .= ', ' . $middleName;
        }

        return $formattedName;
    }

    /**
     * Format employee name for signature as "FIRST MIDDLE LAST"
     * If employee_name format is "Mallari, King Viel, Labro", 
     * then signature format is "KING VIEL LABRO MALLARI"
     */
    private function formatEmployeeNameSignature(Employee $employee)
    {
        $lastName = trim($employee->last_name ?? '');
        $firstName = trim($employee->first_name ?? '');
        $middleName = trim($employee->middle_name ?? '');

        // Format as "First Middle Last" (original case preserved)
        $signatureName = $firstName;

        // Add middle name if available
        if (!empty($middleName)) {
            $signatureName .= ' ' . $middleName;
        }

        // Add last name at the end
        if (!empty($lastName)) {
            $signatureName .= ' ' . $lastName;
        }

        return trim($signatureName);
    }

    /**
     * Calculate employee's daily rate based on fixed_rate and rate_type
     */
    private function calculateEmployeeDailyRate(Employee $employee)
    {
        $fixedRate = $employee->fixed_rate ?? 0;
        $rateType = $employee->rate_type ?? 'daily';

        switch (strtolower($rateType)) {
            case 'daily':
                return $fixedRate;

            case 'weekly':
                // Assuming 6 working days per week
                return $fixedRate / 6;

            case 'monthly':
                // Assuming 22 working days per month (standard in Philippines)
                return $fixedRate / 22;

            case 'semi-monthly':
                // Assuming 11 working days per semi-month
                return $fixedRate / 11;

            case 'hourly':
                // Assuming 8 hours per day
                return $fixedRate * 8;

            default:
                // Default to treating as daily rate
                return $fixedRate;
        }
    }

    /**
     * Calculate additional fields from payroll snapshots for a specific employee and tax year
     */
    private function calculateAdditionalFieldsFromPayrollSnapshots(Employee $employee, $year)
    {
        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = $startDate->copy()->endOfYear();

        // Get all payroll snapshots for the employee for the specified tax year
        $payrollSnapshots = \App\Models\PayrollSnapshot::where('employee_id', $employee->id)
            ->whereHas('payroll', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('period_start', [$startDate, $endDate]);
            })
            ->get();

        $totals = [
            'basic_salary' => 0,
            'holiday_pay' => 0,
            'overtime_pay' => 0,
            'night_differential' => 0,
            'allowances_total' => 0,
            'bonuses_total' => 0,
            'total_deductions' => 0,
        ];

        foreach ($payrollSnapshots as $snapshot) {
            // Field 29: Basic Salary - use 'regular_pay' column
            $totals['basic_salary'] += $snapshot->regular_pay ?? 0;

            // Field 30: Holiday Pay - use 'holiday_pay' column  
            $totals['holiday_pay'] += $snapshot->holiday_pay ?? 0;

            // Field 31: Overtime Pay - calculate from overtime_breakdown JSON (OT only, exclude ND)
            // Field 32: Night Differential - calculate from overtime_breakdown JSON (only with ND)
            if ($snapshot->overtime_breakdown) {
                $overtimeBreakdown = is_string($snapshot->overtime_breakdown)
                    ? json_decode($snapshot->overtime_breakdown, true)
                    : $snapshot->overtime_breakdown;

                if (is_array($overtimeBreakdown)) {
                    foreach ($overtimeBreakdown as $key => $item) {
                        if (isset($item['amount'])) {
                            $amount = $item['amount'] ?? 0;

                            // Field 32: Sum only entries with 'ND' in the key name
                            if (strpos($key, 'ND') !== false) {
                                $totals['night_differential'] += $amount;
                            }
                            // Field 31: Sum only entries with 'OT' in the key name but exclude those with 'ND'
                            elseif (strpos($key, 'OT') !== false && strpos($key, 'ND') === false) {
                                $totals['overtime_pay'] += $amount;
                            }
                        }
                    }
                }
            }

            // Field 34: Bonuses - use 'bonuses_total' column
            $totals['bonuses_total'] += $snapshot->bonuses_total ?? 0;

            // Field 35: Allowances - use 'allowances_total' column
            $totals['allowances_total'] += $snapshot->allowances_total ?? 0;

            // Field 36: Total Deductions - sum all amounts from deductions_breakdown JSON
            if ($snapshot->deductions_breakdown) {
                $deductionsBreakdown = is_string($snapshot->deductions_breakdown)
                    ? json_decode($snapshot->deductions_breakdown, true)
                    : $snapshot->deductions_breakdown;

                if (is_array($deductionsBreakdown)) {
                    foreach ($deductionsBreakdown as $item) {
                        $totals['total_deductions'] += $item['amount'] ?? 0;
                    }
                }
            }
        }

        return $totals;
    }

    /**
     * Calculate automatic fields based on other field values
     */
    private function calculateAutomaticFields(&$formData)
    {
        // Helper function to get numeric value from form field
        $getValue = function ($field) use ($formData) {
            return isset($formData[$field]) ? (float)$formData[$field] : 0;
        };

        // Check if MWE (Minimum Wage Earner) is checked
        $isMWE = isset($formData['mwe']) && $formData['mwe'] === 'Yes';

        if ($isMWE) {
            // If MWE is TRUE/CHECKED: All fields 39-52 must be BLANK
            // Don't set any fields 39-52 (keep them completely blank)
            $formData['52'] = '0'; // Field 52 is blank/zero
        } else {
            // If MWE is FALSE/UNCHECKED: Fields 39, 48, 50, 52 must have values
            $formData['39'] = $formData['29']; // Same as value of field 29 (Basic Salary)
            $formData['48'] = $formData['34']; // Same as value of field 34 (13th Month Pay)
            $formData['50'] = $formData['31']; // Same as value of field 31 (Overtime Pay)

            // Calculate field 52 from the three non-MWE fields
            $field52 = (float)$formData['39'] + (float)$formData['48'] + (float)$formData['50'];
            $formData['52'] = (string)round($field52);
        }

        // Field 38: Sum of fields 29, 30, 31, 32, 33, 34, 35, 36, 37 (always calculate)
        $field38 = $getValue('29') + $getValue('30') + $getValue('31') + $getValue('32') +
            $getValue('33') + $getValue('34') + $getValue('35') + $getValue('36') + $getValue('37');
        $formData['38'] = (string)round($field38);

        // Now calculate all dependent fields using the updated formData values
        // Field 19: Sum of field 38 and 52 (always calculate)
        $field19 = (float)$formData['38'] + (float)$formData['52'];
        $formData['19'] = (string)round($field19);

        // Field 20: Value of field 38 (always calculate)
        $formData['20'] = $formData['38']; // Direct copy

        // Field 21: Value of field 52 (always calculate)
        $formData['21'] = $formData['52']; // Direct copy

        // Field 23: Sum of field 21 and 22 (always calculate)
        $field23 = (float)$formData['21'] + $getValue('22');
        $formData['23'] = (string)round($field23);

        // Field 26: Sum of field 25A and 25B (always calculate)
        $field26 = $getValue('25A') + $getValue('25B');
        $formData['26'] = (string)round($field26);

        // Field 28: Sum of field 26 and 27 (always calculate)
        $field28 = (float)$formData['26'] + $getValue('27');
        $formData['28'] = (string)round($field28);
    }

    /**
     * Create a simple FDF file content for PDFtk form filling
     */
    private function createSimpleFDF($formData)
    {
        $fdf = "%FDF-1.2\n";
        $fdf .= "1 0 obj\n";
        $fdf .= "<<\n";
        $fdf .= "/FDF << /Fields [\n";

        foreach ($formData as $name => $value) {
            $fdf .= "<< /T (" . addcslashes($name, "()\\") . ") /V (" . addcslashes($value, "()\\") . ") >>\n";
        }

        $fdf .= "] >>\n";
        $fdf .= ">>\n";
        $fdf .= "endobj\n";
        $fdf .= "trailer\n";
        $fdf .= "<<\n";
        $fdf .= "/Root 1 0 R\n";
        $fdf .= ">>\n";
        $fdf .= "%%EOF\n";

        return $fdf;
    }

    /**
     * Fill PDF directly using exec command like in your working test script
     */
    private function fillPDFDirectly($formData, $outputPath)
    {
        // Let's use the php-pdftk library but try to bypass the drop_xfa issue
        // by using a different approach that mimics your working script

        try {
            // First approach: Try using the library with explicit no flatten/no drop_xfa
            $pdf = new Pdf($this->pdfTemplatePath);

            // Set options to prevent drop_xfa
            // $pdf->setOptions([
            //     'flatten' => false,
            //     'drop_xfa' => false
            // ]);

            $result = $pdf->fillForm($formData)->saveAs($outputPath);

            if ($result && file_exists($outputPath) && filesize($outputPath) > 0) {
                Log::info('PDFtk library method succeeded without drop_xfa');
                return true;
            }
        } catch (\Exception $e) {
            Log::warning('PDFtk library method failed: ' . $e->getMessage());
        }

        // Fallback: Try to use pdftk binary directly with minimal options
        try {
            // Create a simple data file using key=value format
            $dataPath = storage_path('app/temp/form_data.txt');
            $dataContent = '';

            foreach ($formData as $name => $value) {
                // Simple key=value format, one per line
                $dataContent .= $name . '=' . $value . "\n";
            }

            file_put_contents($dataPath, $dataContent);

            // Try the command exactly as it might work in your standalone script
            $commands = [
                // Method 1: Basic fill_form
                sprintf(
                    'pdftk "%s" fill_form "%s" output "%s"',
                    $this->pdfTemplatePath,
                    $dataPath,
                    $outputPath
                ),

                // Method 2: Try with flatten
                sprintf(
                    'pdftk "%s" fill_form "%s" output "%s" flatten',
                    $this->pdfTemplatePath,
                    $dataPath,
                    $outputPath
                ),

                // Method 3: Try copying first, then filling
                sprintf(
                    'copy "%s" "%s" && pdftk "%s" update_info "%s" output "%s"',
                    $this->pdfTemplatePath,
                    $outputPath,
                    $outputPath,
                    $dataPath,
                    $outputPath . '.tmp'
                )
            ];

            foreach ($commands as $i => $command) {
                Log::info('Trying PDFtk command method ' . ($i + 1) . ': ' . $command);

                $output = [];
                $returnCode = 0;
                exec($command . ' 2>&1', $output, $returnCode);

                Log::info('PDFtk command result', [
                    'method' => $i + 1,
                    'return_code' => $returnCode,
                    'output' => $output,
                    'output_file_exists' => file_exists($outputPath),
                    'output_file_size' => file_exists($outputPath) ? filesize($outputPath) : 0
                ]);

                if ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
                    // Clean up
                    if (file_exists($dataPath)) {
                        unlink($dataPath);
                    }

                    Log::info('PDFtk direct method succeeded with method ' . ($i + 1));
                    return true;
                }
            }

            // Clean up
            if (file_exists($dataPath)) {
                unlink($dataPath);
            }
        } catch (\Exception $e) {
            Log::error('PDFtk direct execution failed: ' . $e->getMessage());
        }

        // If all methods fail, log the failure but don't throw exception
        // Let the calling method handle fallback to empty PDF
        Log::error('All PDFtk methods failed for form filling');
        return false;
    }

    /**
     * Download all employees' forms as individual .xltx files in a ZIP archive.
     * File Name: BIR_2316_EMPLOYEES_2025.zip
     * Each employee gets their own .xltx file with original formatting
     */
    public function downloadAllExcel($employees, $year)
    {
        try {
            // Create a ZIP archive containing individual .xltx files for each employee
            $zipFilename = "BIR_2316_EMPLOYEES_{$year}.zip";
            $zipPath = storage_path('app/temp/' . $zipFilename);

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            // Create ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new \Exception('Cannot create ZIP file');
            }

            // Add individual .xltx file for each employee
            foreach ($employees as $employee) {
                // Create individual filename
                $lastName = strtoupper($employee->last_name);
                $firstName = strtoupper($employee->first_name);
                $middleName = $employee->middle_name ? strtoupper($employee->middle_name) : '';
                $fullName = $middleName ? "{$lastName}_{$firstName}_{$middleName}" : "{$lastName}_{$firstName}";
                $individualFilename = "BIR_2316_{$fullName}_{$year}.xltx";

                // Copy the original template (preserves exact formatting)
                $tempIndividualPath = storage_path('app/temp/' . $individualFilename);
                copy($this->templatePath, $tempIndividualPath);

                // Add to ZIP
                $zip->addFile($tempIndividualPath, $individualFilename);
            }

            // Close ZIP
            $zip->close();

            // Clean up individual temp files
            foreach ($employees as $employee) {
                $lastName = strtoupper($employee->last_name);
                $firstName = strtoupper($employee->first_name);
                $middleName = $employee->middle_name ? strtoupper($employee->middle_name) : '';
                $fullName = $middleName ? "{$lastName}_{$firstName}_{$middleName}" : "{$lastName}_{$firstName}";
                $individualFilename = "BIR_2316_{$fullName}_{$year}.xltx";
                $tempIndividualPath = storage_path('app/temp/' . $individualFilename);
                if (file_exists($tempIndividualPath)) {
                    unlink($tempIndividualPath);
                }
            }

            return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error generating Excel ZIP for all employees: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate Excel files: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download all employees' forms in a single PDF file (multiple pages).
     * File Name: BIR_2316_EMPLOYEES_2025.pdf
     * Each employee gets one page (duplicate copy of the original PDF page)
     */
    public function downloadAllPDF($employees, $year)
    {
        try {
            // Use the exact PDF template file: BIR-23161.pdf (same as individual download)
            if (file_exists($this->pdfTemplatePath)) {
                // Read the original PDF content
                $originalPdfContent = file_get_contents($this->pdfTemplatePath);

                // Create a new PDF with mPDF to combine multiple pages
                $mpdf = new \Mpdf\Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'Legal',
                    'orientation' => 'P'
                ]);

                // For each employee, add the same page content
                foreach ($employees as $index => $employee) {
                    if ($index > 0) {
                        // Add new page for each employee after the first
                        $mpdf->AddPage();
                    }

                    // Import the original PDF page
                    $pagecount = $mpdf->SetSourceFile($this->pdfTemplatePath);
                    for ($i = 1; $i <= $pagecount; $i++) {
                        $template = $mpdf->ImportPage($i);
                        $mpdf->UseTemplate($template);

                        // If there are more pages in the original PDF, add them too
                        if ($i < $pagecount) {
                            $mpdf->AddPage();
                        }
                    }
                }

                // Create the filename: BIR_2316_EMPLOYEES_YEAR.pdf
                $filename = "BIR_2316_EMPLOYEES_{$year}.pdf";
                $tempPath = storage_path('app/temp/' . $filename);

                // Ensure temp directory exists
                if (!file_exists(storage_path('app/temp'))) {
                    mkdir(storage_path('app/temp'), 0755, true);
                }

                // Output the PDF to file
                $mpdf->Output($tempPath, 'F');

                return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
            } else {
                throw new \Exception('PDF template file BIR-23161.pdf not found in storage/app/private/');
            }
        } catch (\Exception $e) {
            Log::error('Error generating PDF for all employees: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download all employees' forms as filled PDF files in a ZIP archive.
     * Each employee gets their own filled PDF with populated payroll data.
     */
    public function downloadAllFilledPDF($employees, $year)
    {
        try {
            // Create a ZIP archive containing filled PDF files for each employee
            $zipFilename = "BIR_2316_FILLED_EMPLOYEES_{$year}.zip";
            $zipPath = storage_path('app/temp/' . $zipFilename);

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            // Initialize ZIP archive
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
                throw new \Exception('Cannot create ZIP file');
            }

            foreach ($employees as $employee) {
                try {
                    // Generate filled PDF for each employee
                    $lastName = strtoupper($employee->last_name);
                    $firstName = strtoupper($employee->first_name);
                    $middleName = $employee->middle_name ? strtoupper($employee->middle_name) : '';

                    $fullName = $middleName ? "{$lastName}_{$firstName}_{$middleName}" : "{$lastName}_{$firstName}";
                    $individualFilename = "BIR_2316_FILLED_{$fullName}_{$year}.pdf";
                    $tempIndividualPath = storage_path('app/temp/' . $individualFilename);

                    // Generate the filled PDF using our existing method
                    $data = $this->generateEmployeeData($employee, $year);
                    $formData = $this->preparePDFFormData($employee, $data, $year);

                    // Try to create filled PDF using complete form data (includes BIR 2316 settings)
                    $result = false;
                    try {
                        // Create a simple FDF file manually using complete form data
                        $fdfContent = $this->createSimpleFDF($formData);
                        $fdfPath = storage_path('app/temp/simple_form_data.fdf');
                        file_put_contents($fdfPath, $fdfContent);

                        // Use PDFtk to fill the form
                        $pdftk = 'C:\Program Files (x86)\PDFtk\bin\pdftk.exe';
                        $command = sprintf(
                            '"%s" "%s" fill_form "%s" output "%s"',
                            $pdftk,
                            $this->pdfTemplatePath,
                            $fdfPath,
                            $tempIndividualPath
                        );

                        exec($command . ' 2>&1', $output, $returnCode);

                        if ($returnCode === 0 && file_exists($tempIndividualPath) && filesize($tempIndividualPath) > 0) {
                            $result = true;
                        }

                        // Clean up FDF file
                        if (file_exists($fdfPath)) {
                            unlink($fdfPath);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to create filled PDF for employee: ' . $employee->employee_number . '. Error: ' . $e->getMessage());
                    }

                    // If filling failed, use empty template
                    if (!$result) {
                        copy($this->pdfTemplatePath, $tempIndividualPath);
                    }

                    // Add to ZIP
                    if (file_exists($tempIndividualPath)) {
                        $zip->addFile($tempIndividualPath, $individualFilename);
                    }
                } catch (\Exception $e) {
                    Log::warning('Error processing employee ' . $employee->employee_number . ': ' . $e->getMessage());
                    // Continue with next employee
                }
            }

            $zip->close();

            // Clean up individual files
            foreach ($employees as $employee) {
                $lastName = strtoupper($employee->last_name);
                $firstName = strtoupper($employee->first_name);
                $middleName = $employee->middle_name ? strtoupper($employee->middle_name) : '';

                $fullName = $middleName ? "{$lastName}_{$firstName}_{$middleName}" : "{$lastName}_{$firstName}";
                $individualFilename = "BIR_2316_FILLED_{$fullName}_{$year}.pdf";
                $tempIndividualPath = storage_path('app/temp/' . $individualFilename);
                if (file_exists($tempIndividualPath)) {
                    unlink($tempIndividualPath);
                }
            }

            return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error generating filled PDF ZIP for all employees: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate filled PDF files: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download all employees' forms as simple PDF files (without FPDI to avoid compression issues).
     * Creates individual PDF copies for each employee.
     */
    public function downloadAllPDFSimple($employees, $year)
    {
        try {
            // Create a ZIP archive containing individual PDF files for each employee
            $zipFilename = "BIR_2316_EMPLOYEES_{$year}.zip";
            $zipPath = storage_path('app/temp/' . $zipFilename);

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            // Initialize ZIP archive
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
                throw new \Exception('Cannot create ZIP file');
            }

            foreach ($employees as $employee) {
                // Create filename for each employee
                $lastName = strtoupper($employee->last_name);
                $firstName = strtoupper($employee->first_name);
                $middleName = $employee->middle_name ? strtoupper($employee->middle_name) : '';

                $fullName = $middleName ? "{$lastName}_{$firstName}_{$middleName}" : "{$lastName}_{$firstName}";
                $individualFilename = "BIR_2316_{$fullName}_{$year}.pdf";
                $tempIndividualPath = storage_path('app/temp/' . $individualFilename);

                // Simply copy the template PDF for each employee (avoids FPDI issues)
                copy($this->pdfTemplatePath, $tempIndividualPath);

                // Add to ZIP
                $zip->addFile($tempIndividualPath, $individualFilename);
            }

            $zip->close();

            // Clean up individual files
            foreach ($employees as $employee) {
                $lastName = strtoupper($employee->last_name);
                $firstName = strtoupper($employee->first_name);
                $middleName = $employee->middle_name ? strtoupper($employee->middle_name) : '';

                $fullName = $middleName ? "{$lastName}_{$firstName}_{$middleName}" : "{$lastName}_{$firstName}";
                $individualFilename = "BIR_2316_{$fullName}_{$year}.pdf";
                $tempIndividualPath = storage_path('app/temp/' . $individualFilename);
                if (file_exists($tempIndividualPath)) {
                    unlink($tempIndividualPath);
                }
            }

            return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error generating simple PDF ZIP for all employees: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate PDF files: ' . $e->getMessage()], 500);
        }
    }
}
