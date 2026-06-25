"use client";
import { useState, useEffect, useMemo, useCallback } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import Image from "next/image";
import DashboardLayout from "@/components/layout/DashboardLayout";
import { useAuth } from "@/contexts/AuthContext";
import { useToast } from "@/contexts/ToastContext";
import {
  ArrowLeft,
  FileText,
  User,
  Eye,
  Maximize,
  Minimize,
  CheckCircle,
  Clock as ClockIcon,
  Plus,
  Trash2,
  Activity,
  ClipboardList,
  Stethoscope,
} from "lucide-react";
import { apiClient } from "@/lib/api";

// Interfaces
interface PatientInfo {
  name: string;
  patientId: string;
  age: string;
  gender: string;
  phone: string;
}

interface LabInfo {
  labId: string;
  requestedBy: string;
  requestedDate: string;
  collectedDate: string;
  analysisDate: string;
  validatedBy: string;
}

interface TestResult {
  id: string;
  category: string;
  testName: string;
  result: string;
  unit: string;
  referenceRange: string;
  flag: "high" | "low" | "critical" | "normal" | null;
}

interface ConsultationInfo {
  paymentType: string;
  physician: string;
  evaluateAt: string;
}

interface VitalSigns {
  systolicBp: string;
  diastolicBp: string;
  pulse: string;
  respiratoryRate: string;
  temperature: string;
  o2Saturation: string;
  height: string;
  weight: string;
}

interface ClinicalRecords {
  chiefComplaint: string;
  currentMedications: string;
  evaluationSummary: string;
}

interface TreatmentPlanItem {
  type: string;
  code: string;
}

interface BackendTestResult {
  category?: string;
  testName?: string;
  result?: string;
  unit?: string;
  referenceRange?: string;
  flag?: string | null;
}

interface BackendLabInfo {
  labId?: string;
  requestedBy?: string;
  requestedDate?: string;
  collectedDate?: string;
  analysisDate?: string;
  validatedBy?: string;
}

interface BackendGroupedTestResults {
  biochemistry?: BackendTestResult[];
  enzymology?: BackendTestResult[];
  hematology?: BackendTestResult[];
  urine_analysis?: BackendTestResult[];
  drug_urine?: BackendTestResult[];
  blood_group?: BackendTestResult[];
  [key: string]: BackendTestResult[] | undefined;
}

interface BackendExtractedData {
  rawText?: string;
  documentType?: 'lab_report' | 'consultation';
  patientInfo?: Partial<PatientInfo>;
  labInfo?: BackendLabInfo;
  // Flat format (legacy regex parser output and normalized batch job output)
  testResults?: BackendTestResult[];
  // Grouped format (Ollama LLM schema output — may appear in old DB records)
  test_results?: BackendGroupedTestResults;
  // Consultation-specific fields
  consultationInfo?: ConsultationInfo;
  vitalSigns?: VitalSigns;
  clinicalRecords?: ClinicalRecords;
  treatmentPlan?: TreatmentPlanItem[];
}

interface BackendLabReport {
  id: number | string;
  status?: string;
  original_filename?: string;
  raw_ocr_text?: string | null;
  document_type?: 'lab_report' | 'consultation' | null;
  uploader?: BackendUploader | null;
  batch?: {
    id: number;
    name: string;
    status: string;
  } | null;
}

interface BackendUploader {
  name?: string;
}

interface ApiError {
  response?: {
    status?: number;
    data?: {
      message?: string;
      errors?: Record<string, string[]>;
    };
  };
  status?: number;
  message?: string;
}

const isApiError = (err: unknown): err is ApiError =>
  typeof err === "object" && err !== null;

const mapBackendFlag = (flag: string | null): TestResult["flag"] => {
  if (!flag) return null;
  switch (flag.toUpperCase()) {
    case "H":
      return "high";
    case "L":
      return "low";
    case "C":
      return "critical";
    default:
      return "normal";
  }
};

const mapFrontendFlag = (flag: TestResult["flag"]): string | null => {
  if (!flag || flag === "normal") return null;
  switch (flag) {
    case "high":
      return "H";
    case "low":
      return "L";
    case "critical":
      return "C";
    default:
      return null;
  }
};

interface ProcessedReport {
  id: string;
  fileName: string;
  status: "processing" | "completed" | "verified" | "error";
  processingProgress: number;
  pdfUrl: string; // Will be updated dynamically with data URL
  documentType: 'lab_report' | 'consultation';
  patientInfo: PatientInfo;
  labInfo: LabInfo;
  testResults: TestResult[];
  consultationInfo?: ConsultationInfo;
  vitalSigns?: VitalSigns;
  clinicalRecords?: ClinicalRecords;
  treatmentPlan?: TreatmentPlanItem[];
  extracted_data?: BackendExtractedData;
  original_filename?: string;
  uploader?: BackendUploader | null;
  rawOcrText?: string;
}

interface BatchInfo {
  id: number;
  name: string;
  status: string;
}

export default function VerificationPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const batchId = searchParams.get("batchId");
  const reportId = searchParams.get("reportId");
  const { user } = useAuth();
  const toast = useToast();

  const [reports, setReports] = useState<ProcessedReport[]>([]);
  const [selectedReport, setSelectedReport] = useState<ProcessedReport | null>(
    null,
  );
  const [isPreviewExpanded, setIsPreviewExpanded] = useState(false);
  const [batchInfo, setBatchInfo] = useState<BatchInfo | null>(null);
  const [pdfDataUrl, setPdfDataUrl] = useState<string>("");
  const [pdfLoading, setPdfLoading] = useState(false);
  const [pdfError, setPdfError] = useState("");
  const [fileContentType, setFileContentType] = useState<string>("");
  const [pageLoading, setPageLoading] = useState(true);
  const [pageError, setPageError] = useState("");
  const [isProcessing, setIsProcessing] = useState(false);

  // Modal states
  const [showCategoryModal, setShowCategoryModal] = useState(false);
  const [newCategoryName, setNewCategoryName] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Fallback mock user data
  const mockUser = {
    name: "Dr. Sarah Johnson",
    email: "sarah@smithclinic.com",
    clinic: "Smith Medical Clinic",
  };

  // Use auth user if available, otherwise fallback to mock
  const currentUser = user
    ? {
        name: user.name,
        email: user.email,
        clinic: "Smith Medical Clinic",
      }
    : mockUser;

  // Group test results by category - use useMemo to avoid recalculation
  const groupedTestResults = useMemo(() => {
    if (!selectedReport) return {};

    return selectedReport.testResults.reduce(
      (acc, test) => {
        const category = test.category || "UNCATEGORIZED";
        if (!acc[category]) {
          acc[category] = [];
        }
        acc[category].push(test);
        return acc;
      },
      {} as Record<string, TestResult[]>,
    );
  }, [selectedReport]);

  const fetchPdfData = useCallback(async (reportId: string) => {
    try {
      setPdfLoading(true);
      setPdfError("");
      setFileContentType("");
      const response = await apiClient.get(`/${reportId}/pdf-data`);
      if (response.success && response.data?.base64_content) {
        const base64 = response.data.base64_content;
        const contentType = response.data.content_type || "application/pdf";
        setFileContentType(contentType);
        const dataUrl = `data:${contentType};base64,${base64}`;
        setPdfDataUrl(dataUrl);
      } else {
        throw new Error("Invalid file response structure");
      }
    } catch (err: unknown) {
      console.error("Failed to fetch file data:", err);
      let errorMessage = "Failed to load document preview";

      if (isApiError(err) && err.status === 404) {
        errorMessage = "File not found";
      } else if (isApiError(err) && err.status === 401) {
        errorMessage = "Authentication failed. Please log in again.";
      } else if (isApiError(err) && err.message) {
        errorMessage = err.message;
      }

      setPdfError(errorMessage);
    } finally {
      setPdfLoading(false);
    }
  }, []);

  const transformLabReportToProcessedReport = useCallback(
    (
      labReport: BackendLabReport,
      extractedData?: BackendExtractedData,
    ): ProcessedReport => {
      const rawOcrText = labReport.raw_ocr_text || extractedData?.rawText || (extractedData as any)?.raw_text || (extractedData as any)?._raw_text || "";

      // Determine document type from backend field or extracted data
      const isConsult =
        labReport.document_type === 'consultation' ||
        labReport.document_type === 'Patient Consultation Information' ||
        extractedData?.documentType === 'consultation' ||
        extractedData?.documentType === 'Patient Consultation Information' ||
        (extractedData as any)?.document_type === 'Patient Consultation Information';

      const docType: 'lab_report' | 'consultation' = isConsult ? 'consultation' : 'lab_report';

      const transformed: ProcessedReport = {
        id: labReport.id.toString(),
        fileName: labReport.original_filename || `Report ${labReport.id}`,
        status:
          labReport.status === "verified"
            ? ("verified" as const)
            : labReport.status === "processed"
              ? ("completed" as const)
              : ("completed" as const),
        processingProgress: 100,
        pdfUrl: "",
        documentType: docType,
        patientInfo: {
          name: extractedData?.patientInfo?.name || "",
          patientId: extractedData?.patientInfo?.patientId || "",
          age: extractedData?.patientInfo?.age || "",
          gender: extractedData?.patientInfo?.gender || "",
          phone: extractedData?.patientInfo?.phone || "",
        },
        labInfo: {
          labId: extractedData?.labInfo?.labId || "",
          requestedBy: extractedData?.labInfo?.requestedBy || "",
          requestedDate: extractedData?.labInfo?.requestedDate || "",
          collectedDate: extractedData?.labInfo?.collectedDate || "",
          analysisDate: extractedData?.labInfo?.analysisDate || "",
          validatedBy: extractedData?.labInfo?.validatedBy || "",
        },
        testResults: (() => {
          // Prefer flat testResults; fall back to flattening grouped test_results
          let flatTests: BackendTestResult[] = extractedData?.testResults || [];

          if (flatTests.length === 0 && extractedData?.test_results) {
            const grouped = extractedData.test_results;
            const allTests: BackendTestResult[] = [];
            Object.entries(grouped).forEach(([panel, tests]) => {
              if (Array.isArray(tests)) {
                tests.forEach((test) => {
                  allTests.push({
                    ...test,
                    category: test.category || panel.toUpperCase(),
                  });
                });
              }
            });
            flatTests = allTests;
          }

          return flatTests.map((test: BackendTestResult, index: number) => ({
            id: `${labReport.id}_${index}`,
            category: test.category || "",
            testName: test.testName || "",
            result: test.result || "",
            unit: test.unit || "",
            referenceRange: test.referenceRange || "",
            flag: mapBackendFlag(test.flag ?? null),
          }));
        })(),
        // Consultation-specific fields
        ...(docType === 'consultation' ? {
          consultationInfo: {
            paymentType: extractedData?.consultationInfo?.paymentType || "",
            physician: extractedData?.consultationInfo?.physician || "",
            evaluateAt: extractedData?.consultationInfo?.evaluateAt || "",
          },
          vitalSigns: {
            systolicBp: extractedData?.vitalSigns?.systolicBp || "",
            diastolicBp: extractedData?.vitalSigns?.diastolicBp || "",
            pulse: extractedData?.vitalSigns?.pulse || "",
            respiratoryRate: extractedData?.vitalSigns?.respiratoryRate || "",
            temperature: extractedData?.vitalSigns?.temperature || "",
            o2Saturation: extractedData?.vitalSigns?.o2Saturation || "",
            height: extractedData?.vitalSigns?.height || "",
            weight: extractedData?.vitalSigns?.weight || "",
          },
          clinicalRecords: {
            chiefComplaint: extractedData?.clinicalRecords?.chiefComplaint || "",
            currentMedications: extractedData?.clinicalRecords?.currentMedications || "",
            evaluationSummary: extractedData?.clinicalRecords?.evaluationSummary || "",
          },
          treatmentPlan: extractedData?.treatmentPlan || [],
        } : {}),
        extracted_data: extractedData,
        original_filename: labReport.original_filename,
        uploader: labReport.uploader,
        rawOcrText,
      };


      return transformed;
    },
    [],
  );

  const handleFetchError = useCallback((err: unknown, context: string) => {
    let errorMessage = `Failed to load ${context}`;

    if (isApiError(err) && err.response?.status === 404) {
      errorMessage = `${context} not found or has no reports ready for verification`;
    } else if (isApiError(err) && err.response?.status === 401) {
      errorMessage = "Authentication failed. Please log in again.";
    } else if (isApiError(err) && err.response?.status === 403) {
      errorMessage = "You do not have permission to access this resource";
    } else if (isApiError(err) && err.response?.data?.message) {
      errorMessage = err.response.data.message;
    } else if (isApiError(err) && err.message) {
      errorMessage = err.message;
    }

    console.error(errorMessage);
  }, []);

  const fetchSingleReport = useCallback(
    async (id: string) => {
      try {
        const response = await apiClient.get(`/lab-reports/${id}`);
        if (response.success && response.data?.lab_report) {
          const labReport: BackendLabReport = response.data.lab_report;
          const extractedData: BackendExtractedData | undefined =
            response.data.extracted_data;
          const transformedReport = transformLabReportToProcessedReport(
            labReport,
            extractedData,
          );

          setReports([transformedReport]);
          setSelectedReport(transformedReport);

          // Fetch PDF data for the selected report
          await fetchPdfData(id);

          if (labReport.batch) {
            setBatchInfo({
              id: labReport.batch.id,
              name: labReport.batch.name,
              status: labReport.batch.status,
            });
          }
        } else {
          throw new Error("Invalid response structure");
        }
      } catch (err: unknown) {
        console.error("Failed to fetch single report:", err);
        handleFetchError(err, `report ${id}`);
        setPageError(`Failed to load report. Please try again.`);
      } finally {
        setPageLoading(false);
      }
    },
    [fetchPdfData, handleFetchError, transformLabReportToProcessedReport],
  );

  const fetchBatchReports = useCallback(async (isPolling = false) => {
    try {
      const response = await apiClient.get(
        `/batches/${batchId}/reports-for-verification`,
      );
      const apiResponse = response.data;

      if (
        !apiResponse ||
        !apiResponse.batch ||
        !apiResponse.reports_to_verify?.data
      ) {
        throw new Error("Invalid API response structure");
      }

      setBatchInfo(apiResponse.batch);

      const reportsData: Array<
        BackendLabReport & { extracted_data?: BackendExtractedData }
      > = apiResponse.reports_to_verify.data;
      const transformedReports = reportsData.map((report) =>
        transformLabReportToProcessedReport(report, report.extracted_data),
      );

      setReports((prevReports) => {
        if (isPolling) {
          const prevReportsMap = new Map(prevReports.map(r => [r.id, r]));
          return transformedReports.map(tr => {
            const existing = prevReportsMap.get(tr.id);
            if (existing) {
              if (existing.status !== tr.status) {
                return tr;
              }
              return existing;
            }
            return tr;
          });
        }
        return transformedReports;
      });

      // Check if batch is still processing
      const batchStatus = apiResponse.batch?.status || '';
      if (batchStatus === 'processing' || batchStatus === 'pending') {
        setIsProcessing(true);
      } else {
        setIsProcessing(false);
      }

      if (!isPolling && transformedReports.length > 0) {
        const targetReport = reportId
          ? transformedReports.find((r: ProcessedReport) => r.id === reportId)
          : transformedReports[0];
        setSelectedReport(targetReport || transformedReports[0]);
        if (targetReport) {
          await fetchPdfData(targetReport.id);
        }
      }
    } catch (err: unknown) {
      console.error("Failed to fetch batch reports:", err);
      if (!isPolling) {
        handleFetchError(err, `batch ${batchId}`);
        setPageError(`Failed to load batch reports. Please try again.`);
      }
    } finally {
      if (!isPolling) {
        setPageLoading(false);
      }
    }
  }, [
    batchId,
    fetchPdfData,
    handleFetchError,
    transformLabReportToProcessedReport,
    reportId,
  ]);

  const fetchAllReports = useCallback(() => {
    const mockReports: ProcessedReport[] = [
      {
        id: "rpt_001",
        fileName: "lab_report_john_doe.pdf",
        status: "completed",
        processingProgress: 100,
        pdfUrl: "", // Placeholder for mock data
        documentType: 'lab_report',
        patientInfo: {
          name: "សាន សេងយាន",
          patientId: "PT001871",
          age: "72 Y",
          gender: "Female",
          phone: "069366717",
        },
        labInfo: {
          labId: "LT001235",
          requestedBy: "Dr. CHHORN Sophy",
          requestedDate: "17/03/2024 12:57",
          collectedDate: "17/03/2024 13:36",
          analysisDate: "17/03/2024 13:36",
          validatedBy: "SREYNEANG - B.Sc",
        },
        testResults: [
          {
            id: "1",
            category: "BIOCHEMISTRY",
            testName: "Glucose",
            result: "6.5",
            unit: "mmol/L",
            referenceRange: "(3.9-6.1)",
            flag: "high",
          },
        ],
      },
    ];

    setTimeout(() => {
      setReports(mockReports);
      const firstCompleted = mockReports.find((r) => r.status === "completed");
      if (firstCompleted) {
        setSelectedReport(firstCompleted);
        // Skip PDF fetching for mock data
        setPdfDataUrl("");
        setPdfError("PDF preview not available for mock data");
      }
    }, 3000);
  }, []);

  useEffect(() => {
    setPageLoading(true);
    setPageError("");
    if (reportId) {
      fetchSingleReport(reportId);
    } else if (batchId) {
      fetchBatchReports();
    } else {
      fetchAllReports();
      setPageLoading(false);
    }
  }, [
    batchId,
    reportId,
    fetchAllReports,
    fetchBatchReports,
    fetchSingleReport,
  ]);

  // Auto-retry polling when batch is still processing
  useEffect(() => {
    if (!isProcessing) return;
    const interval = setInterval(() => {
      fetchBatchReports(true);
    }, 5000);
    return () => clearInterval(interval);
  }, [isProcessing, fetchBatchReports]);

  // Helper functions for updating data
  const updatePatientInfo = (field: keyof PatientInfo, value: string) => {
    if (!selectedReport) return;

    const updatedReport = {
      ...selectedReport,
      patientInfo: {
        ...selectedReport.patientInfo,
        [field]: value,
      },
    };

    setSelectedReport(updatedReport);
    setReports((prev) =>
      prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
    );
  };

  const updateLabInfo = (field: keyof LabInfo, value: string) => {
    if (!selectedReport) return;

    const updatedReport = {
      ...selectedReport,
      labInfo: {
        ...selectedReport.labInfo,
        [field]: value,
      },
    };

    setSelectedReport(updatedReport);
    setReports((prev) =>
      prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
    );
  };

  const updateTestResult = (
    testId: string,
    field: keyof TestResult,
    value: string | null,
  ) => {
    if (!selectedReport) return;

    const updatedReport = {
      ...selectedReport,
      testResults: selectedReport.testResults.map((test) =>
        test.id === testId ? { ...test, [field]: value } : test,
      ),
    };

    setSelectedReport(updatedReport);
    setReports((prev) =>
      prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
    );
  };

  const updateConsultationInfo = (field: keyof ConsultationInfo, value: string) => {
    if (!selectedReport) return;

    const updatedReport = {
      ...selectedReport,
      consultationInfo: {
        ...selectedReport.consultationInfo!,
        [field]: value,
      },
    };

    setSelectedReport(updatedReport);
    setReports((prev) =>
      prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
    );
  };

  const updateVitalSigns = (field: keyof VitalSigns, value: string) => {
    if (!selectedReport) return;

    const updatedReport = {
      ...selectedReport,
      vitalSigns: {
        ...selectedReport.vitalSigns!,
        [field]: value,
      },
    };

    setSelectedReport(updatedReport);
    setReports((prev) =>
      prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
    );
  };

  const updateClinicalRecords = (field: keyof ClinicalRecords, value: string) => {
    if (!selectedReport) return;

    const updatedReport = {
      ...selectedReport,
      clinicalRecords: {
        ...selectedReport.clinicalRecords!,
        [field]: value,
      },
    };

    setSelectedReport(updatedReport);
    setReports((prev) =>
      prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
    );
  };

  const updateTreatmentPlanItem = (index: number, field: keyof TreatmentPlanItem, value: string) => {
    if (!selectedReport || !selectedReport.treatmentPlan) return;

    const updatedPlan = selectedReport.treatmentPlan.map((item, i) =>
      i === index ? { ...item, [field]: value } : item,
    );

    const updatedReport = {
      ...selectedReport,
      treatmentPlan: updatedPlan,
    };

    setSelectedReport(updatedReport);
    setReports((prev) =>
      prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
    );
  };

  const addTreatmentPlanItem = () => {
    if (!selectedReport) return;

    const updatedReport = {
      ...selectedReport,
      treatmentPlan: [
        ...(selectedReport.treatmentPlan || []),
        { type: "", code: "" },
      ],
    };

    setSelectedReport(updatedReport);
    setReports((prev) =>
      prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
    );
  };

  const removeTreatmentPlanItem = (index: number) => {
    if (!selectedReport || !selectedReport.treatmentPlan) return;

    const updatedReport = {
      ...selectedReport,
      treatmentPlan: selectedReport.treatmentPlan.filter((_, i) => i !== index),
    };

    setSelectedReport(updatedReport);
    setReports((prev) =>
      prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
    );
  };

  const addNewCategory = () => {
    setShowCategoryModal(true);
  };

  const handleCategorySubmit = () => {
    if (!newCategoryName.trim() || !selectedReport) return;

    const newTest: TestResult = {
      id: `test_${Date.now()}`,
      category: newCategoryName.toUpperCase(),
      testName: "",
      result: "",
      unit: "",
      referenceRange: "",
      flag: null,
    };

    const updatedReport = {
      ...selectedReport,
      testResults: [...selectedReport.testResults, newTest],
    };

    setSelectedReport(updatedReport);
    setReports((prev) =>
      prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
    );
    setNewCategoryName("");
    setShowCategoryModal(false);
  };

  const addTestResult = (category: string) => {
    if (!selectedReport) return;

    const newTest: TestResult = {
      id: `test_${Date.now()}`,
      category,
      testName: "",
      result: "",
      unit: "",
      referenceRange: "",
      flag: null,
    };

    const updatedReport = {
      ...selectedReport,
      testResults: [...selectedReport.testResults, newTest],
    };

    setSelectedReport(updatedReport);
    setReports((prev) =>
      prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
    );
  };

  const removeTestResult = (testId: string) => {
    if (!selectedReport) return;

    const updatedReport = {
      ...selectedReport,
      testResults: selectedReport.testResults.filter(
        (test) => test.id !== testId,
      ),
    };

    setSelectedReport(updatedReport);
    setReports((prev) =>
      prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
    );
  };

  const handleSubmitVerification = async () => {
    if (!selectedReport) return;

    setIsSubmitting(true);

    try {

      const isConsultation = selectedReport.documentType === 'consultation';

      const verifiedData = {
        verified_data: {
          documentType: selectedReport.documentType,
          patientInfo: {
            name: selectedReport.patientInfo.name,
            patientId: selectedReport.patientInfo.patientId,
            age: selectedReport.patientInfo.age,
            gender: selectedReport.patientInfo.gender,
            phone: selectedReport.patientInfo.phone,
          },
          ...(isConsultation
            ? {
                consultationInfo: selectedReport.consultationInfo || { paymentType: "", physician: "", evaluateAt: "" },
                vitalSigns: selectedReport.vitalSigns || { systolicBp: "", diastolicBp: "", pulse: "", respiratoryRate: "", temperature: "", o2Saturation: "", height: "", weight: "" },
                clinicalRecords: selectedReport.clinicalRecords || { chiefComplaint: "", currentMedications: "", evaluationSummary: "" },
                treatmentPlan: selectedReport.treatmentPlan || [],
              }
            : {
                labInfo: {
                  labId: selectedReport.labInfo.labId,
                  requestedBy: selectedReport.labInfo.requestedBy,
                  requestedDate: selectedReport.labInfo.requestedDate,
                  collectedDate: selectedReport.labInfo.collectedDate,
                  analysisDate: selectedReport.labInfo.analysisDate,
                  validatedBy: selectedReport.labInfo.validatedBy,
                },
                testResults: selectedReport.testResults.map((test) => ({
                  category: test.category,
                  testName: test.testName,
                  result: test.result,
                  unit: test.unit,
                  referenceRange: test.referenceRange,
                  flag: mapFrontendFlag(test.flag),
                })),
              }),
        },
        notes: `Verified by ${currentUser.name} on ${new Date().toLocaleDateString()}`,
      };
      const response = await apiClient.post(
        `/lab-reports/${selectedReport.id}/verify`,
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        verifiedData as any,
      );
      if (response.success) {
        const updatedReport = {
          ...selectedReport,
          status: "verified" as const,
        };

        setSelectedReport(updatedReport);
        setReports((prev) =>
          prev.map((r) => (r.id === selectedReport.id ? updatedReport : r)),
        );

        if (batchId) {
          const remainingReports = reports.filter(
            (r) => r.id !== selectedReport.id && r.status === "completed",
          );

          if (remainingReports.length > 0) {
            setSelectedReport(remainingReports[0]);

            const newUrl = `/main/verification?batchId=${batchId}&reportId=${remainingReports[0].id}`;
            window.history.replaceState(null, "", newUrl);

            // Fetch PDF for the next report
            await fetchPdfData(remainingReports[0].id);
          } else {
            router.push("/main/verification/monitoring");
          }
        } else {
          router.push("/main/reports?status=verified");
        }
      } else {
        throw new Error(response.message || "Verification failed");
      }
    } catch (err: unknown) {
      console.error("Verification submission failed:", err);

      let errorMessage = "Failed to verify report";

      if (isApiError(err) && err.response?.status === 422) {
        const validationErrors = err.response.data?.errors;
        if (validationErrors) {
          errorMessage =
            "Validation failed: " +
            Object.values(validationErrors).flat().join(", ");
        } else {
          errorMessage = err.response.data?.message || "Validation failed";
        }
      } else if (isApiError(err) && err.response?.status === 401) {
        errorMessage = "Authentication failed. Please log in again.";
      } else if (isApiError(err) && err.response?.status === 404) {
        errorMessage = "Report not found";
      } else if (isApiError(err) && err.response?.data?.message) {
        errorMessage = err.response.data.message;
      } else if (isApiError(err) && err.message) {
        errorMessage = err.message;
      }

      toast.error(`Verification Error: ${errorMessage}`);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleVerifyLater = async () => {
    if (!selectedReport) return;

    setIsSubmitting(true);

    try {

      if (batchId) {
        router.push("/main/verification/monitoring");
      } else {
        router.push("/main/reports?status=processed");
      }
    } catch (err: unknown) {
      console.error("Failed to save for later:", err);
      toast.error("Failed to save report for later verification");
    } finally {
      setIsSubmitting(false);
    }
  };

  const renderFlagDropdown = (test: TestResult) => (
    <select
      value={test.flag || ""}
      onChange={(e) => {
        const value = e.target.value === "" ? null : e.target.value;
        updateTestResult(test.id, "flag", value as TestResult["flag"]);
      }}
      className="block w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900"
    >
      <option value="">Normal</option>
      <option value="H">High (H)</option>
      <option value="L">Low (L)</option>
    </select>
  );

  const copyRawText = async () => {
    if (!selectedReport?.rawOcrText) return;

    try {
      await navigator.clipboard.writeText(selectedReport.rawOcrText);
    } catch (error) {
      console.error("Failed to copy raw OCR text:", error);
    }
  };

  // Full-page loading state
  if (pageLoading) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center min-h-[60vh]">
          <div className="text-center space-y-4">
            <div className="relative">
              <div className="animate-spin rounded-full h-16 w-16 border-4 border-blue-200 border-t-blue-600 mx-auto"></div>
            </div>
            <h3 className="text-lg font-medium text-gray-900">Loading Report Data</h3>
            <p className="text-sm text-gray-500 max-w-sm">
              Fetching extracted data and document preview. This may take a moment if the report is still being processed...
            </p>
          </div>
        </div>
      </DashboardLayout>
    );
  }

  // Error state with retry
  if (pageError && reports.length === 0) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center min-h-[60vh]">
          <div className="text-center space-y-4 max-w-md">
            <div className="bg-amber-50 rounded-full h-16 w-16 flex items-center justify-center mx-auto">
              <FileText className="h-8 w-8 text-amber-500" />
            </div>
            <h3 className="text-lg font-medium text-gray-900">Report Not Ready Yet</h3>
            <p className="text-sm text-gray-500">
              The report may still be processing. Please wait a moment and try again.
            </p>
            <div className="flex space-x-3 justify-center">
              <button
                onClick={() => {
                  setPageLoading(true);
                  setPageError("");
                  if (reportId) fetchSingleReport(reportId);
                  else if (batchId) fetchBatchReports();
                }}
                className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              >
                <ArrowLeft className="h-4 w-4 mr-2 animate-spin" style={{ animation: 'none' }} />
                Try Again
              </button>
              <button
                onClick={() => router.push('/main/verification/monitoring')}
                className="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50"
              >
                Back to Monitoring
              </button>
            </div>
          </div>
        </div>
      </DashboardLayout>
    );
  }

  // Processing state — batch exists but reports aren't ready yet
  if (isProcessing && !pageLoading && reports.length === 0) {
    return (
      <DashboardLayout>
        <div className="space-y-6">
          {/* Header */}
          <div className="flex items-center space-x-4">
            <button
              onClick={() => router.push('/main/verification/monitoring')}
              className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Monitoring
            </button>
            <div>
              <h1 className="text-3xl font-bold text-gray-900">
                Batch Verification - {batchInfo?.name || `Batch ${batchId}`}
              </h1>
            </div>
          </div>

          {/* Processing Animation */}
          <div className="bg-white shadow rounded-lg p-12">
            <div className="flex flex-col items-center justify-center space-y-6">
              <div className="relative">
                <div className="animate-spin rounded-full h-20 w-20 border-4 border-blue-200 border-t-blue-600"></div>
                <div className="absolute inset-0 flex items-center justify-center">
                  <FileText className="h-8 w-8 text-blue-600" />
                </div>
              </div>
              <div className="text-center space-y-2">
                <h3 className="text-xl font-semibold text-gray-900">Processing Your Reports</h3>
                <p className="text-gray-500 max-w-md">
                  The OCR engine is extracting data from your uploaded documents. This page will automatically update once processing is complete.
                </p>
              </div>
              <div className="flex items-center space-x-2 text-sm text-blue-600 bg-blue-50 px-4 py-2 rounded-full">
                <div className="animate-pulse h-2 w-2 rounded-full bg-blue-600"></div>
                <span>Auto-refreshing every 5 seconds...</span>
              </div>
            </div>
          </div>
        </div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <button
              onClick={() => {
                if (reportId && !batchId) {
                  router.push("/main/reports");
                } else {
                  router.push("/main/verification/monitoring");
                }
              }}
              className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <ArrowLeft className="h-4 w-4 mr-2" />
              {reportId && !batchId ? "Back to Reports" : "Back to Monitoring"}
            </button>
            <div>
              <h1 className="text-3xl font-bold text-gray-900">
                {batchId
                  ? `Batch Verification - ${batchInfo?.name || `Batch ${batchId}`}`
                  : "Data Verification"}
              </h1>
              <p className="mt-1 text-gray-600">
                {batchId
                  ? `Review and verify ${reports.length} reports from this batch`
                  : reportId
                    ? "Review and verify this report"
                    : "Review and verify extracted data before submission"}
              </p>
            </div>
          </div>
          <div className="flex space-x-3">
            <button
              onClick={handleVerifyLater}
              disabled={
                !selectedReport ||
                selectedReport.status !== "completed" ||
                isSubmitting
              }
              className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isSubmitting ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-400 mr-2"></div>
                  Saving...
                </>
              ) : (
                <>
                  <ClockIcon className="h-4 w-4 mr-2" />
                  Verify Later
                </>
              )}
            </button>
            <button
              onClick={handleSubmitVerification}
              disabled={
                !selectedReport ||
                selectedReport.status !== "completed" ||
                isSubmitting
              }
              className="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isSubmitting ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                  Submitting...
                </>
              ) : (
                <>
                  <CheckCircle className="h-4 w-4 mr-2" />
                  Submit Verification
                </>
              )}
            </button>
          </div>
        </div>

        {/* Batch Info Banner */}
        {batchInfo && (
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div className="flex items-center space-x-4">
              <div className="shrink-0">
                <FileText className="h-8 w-8 text-blue-600" />
              </div>
              <div className="flex-1">
                <h3 className="text-lg font-medium text-blue-900">
                  {batchInfo.name}
                </h3>
                <p className="text-blue-700">
                  Verifying {reports.length} reports from this batch
                </p>
              </div>
              <div className="shrink-0">
                <span
                  className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${
                    batchInfo.status === "completed"
                      ? "bg-green-100 text-green-800"
                      : batchInfo.status === "completed_with_errors"
                        ? "bg-amber-100 text-amber-800"
                        : batchInfo.status === "processing"
                          ? "bg-blue-100 text-blue-800"
                          : batchInfo.status === "failed"
                            ? "bg-red-100 text-red-800"
                            : "bg-yellow-100 text-yellow-800"
                  }`}
                >
                  {batchInfo.status === "completed_with_errors"
                    ? "completed (with errors)"
                    : batchInfo.status}
                </span>
              </div>
            </div>
          </div>
        )}

        {/* Main Content Grid - Redesigned Layout */}
        <div className="grid grid-cols-1 xl:grid-cols-12 gap-6">
          {/* PDF Preview - Left Side */}
          <div className="xl:col-span-5">
            <div className="bg-white shadow rounded-lg sticky top-6">
              <div className="px-6 py-4 border-b border-gray-200">
                <div className="flex items-center justify-between">
                  <h3 className="text-lg font-medium text-gray-900 flex items-center">
                    <Eye className="h-5 w-5 mr-2 text-orange-600" />
                    Document Preview
                  </h3>
                  <div className="flex items-center space-x-2">
                    <button
                      onClick={() => window.open(pdfDataUrl, "_blank")}
                      className="text-blue-600 hover:text-blue-800 font-medium text-sm"
                      disabled={!pdfDataUrl}
                    >
                      {fileContentType.startsWith("image/") ? "Open Full Image" : "Open Full PDF"}
                    </button>
                    <button
                      onClick={() => setIsPreviewExpanded(!isPreviewExpanded)}
                      className="text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md p-1"
                      title={isPreviewExpanded ? "Minimize" : "Expand"}
                    >
                      {isPreviewExpanded ? (
                        <Minimize className="h-4 w-4" />
                      ) : (
                        <Maximize className="h-4 w-4" />
                      )}
                    </button>
                  </div>
                </div>
                {selectedReport && (
                  <p className="text-sm text-gray-500 mt-1">
                    {selectedReport.fileName}
                  </p>
                )}
              </div>
              <div className="p-4">
                {selectedReport ? (
                  pdfLoading ? (
                    <div className="flex items-center justify-center h-96">
                      <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                      <span className="ml-3 text-gray-600">Loading document...</span>
                    </div>
                  ) : pdfError ? (
                    <div className="h-96 flex items-center justify-center bg-gray-50 rounded-md border border-gray-200">
                      <div className="text-center">
                        <FileText className="h-8 w-8 text-gray-400 mx-auto mb-2" />
                        <p className="text-sm text-red-600">{pdfError}</p>
                      </div>
                    </div>
                  ) : (
                    <div
                      className={`${isPreviewExpanded ? "h-200" : "h-175"} transition-all duration-300`}
                    >
                      {fileContentType.startsWith("image/") ? (
                        <div className="relative w-full h-full rounded-md border border-gray-200 shadow-sm bg-gray-50 overflow-hidden">
                          <Image
                            src={pdfDataUrl}
                            alt="Lab Report Preview"
                            fill
                            sizes="100vw"
                            className="object-contain"
                            unoptimized
                          />
                        </div>
                      ) : (
                        <iframe
                          src={pdfDataUrl}
                          className="w-full h-full rounded-md border border-gray-200 shadow-sm"
                          title="PDF Preview"
                        />
                      )}
                    </div>
                  )
                ) : (
                  <div className="h-96 flex items-center justify-center bg-gray-50 rounded-md border border-gray-200">
                    <div className="text-center">
                      <FileText className="h-8 w-8 text-gray-400 mx-auto mb-2" />
                      <p className="text-sm text-gray-500">
                        Select a report to preview
                      </p>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Main Content Area - Middle */}
          <div className="xl:col-span-5">
            {selectedReport ? (
              <div className="space-y-6">
                {/* Patient Information */}
                <div className="bg-white shadow rounded-lg">
                  <div className="px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-medium text-gray-900 flex items-center">
                      <User className="h-5 w-5 mr-2 text-blue-600" />
                      Patient Information
                    </h3>
                  </div>
                  <div className="p-6 grid grid-cols-2 gap-4">
                    {Object.entries(selectedReport.patientInfo).map(
                      ([key, value]) => (
                        <div key={key}>
                          <label className="block text-sm font-medium text-gray-700 mb-1 capitalize">
                            {key.replace(/([A-Z])/g, " $1").trim()}
                          </label>
                          <input
                            type="text"
                            value={value}
                            onChange={(e) =>
                              updatePatientInfo(
                                key as keyof PatientInfo,
                                e.target.value,
                              )
                            }
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder={`Enter ${key
                              .replace(/([A-Z])/g, " $1")
                              .trim()
                              .toLowerCase()}`}
                          />
                        </div>
                      ),
                    )}
                  </div>
                </div>

                {/* Conditional rendering: Consultation vs Lab Report */}
                {selectedReport.documentType === 'consultation' ? (
                  <>
                    {/* Consultation Details */}
                    <div className="bg-white shadow rounded-lg">
                      <div className="px-6 py-4 border-b border-gray-200">
                        <h3 className="text-lg font-medium text-gray-900 flex items-center">
                          <Stethoscope className="h-5 w-5 mr-2 text-green-600" />
                          Consultation Details
                        </h3>
                      </div>
                      <div className="p-6 grid grid-cols-2 gap-4">
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Payment Type
                          </label>
                          <input
                            type="text"
                            value={selectedReport.consultationInfo?.paymentType || ""}
                            onChange={(e) => updateConsultationInfo("paymentType", e.target.value)}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter payment type"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Physician
                          </label>
                          <input
                            type="text"
                            value={selectedReport.consultationInfo?.physician || ""}
                            onChange={(e) => updateConsultationInfo("physician", e.target.value)}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter physician"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Evaluate At
                          </label>
                          <input
                            type="text"
                            value={selectedReport.consultationInfo?.evaluateAt || ""}
                            onChange={(e) => updateConsultationInfo("evaluateAt", e.target.value)}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter evaluation date/time"
                          />
                        </div>
                      </div>
                    </div>

                    {/* Vital Signs */}
                    <div className="bg-white shadow rounded-lg">
                      <div className="px-6 py-4 border-b border-gray-200">
                        <h3 className="text-lg font-medium text-gray-900 flex items-center">
                          <Activity className="h-5 w-5 mr-2 text-red-600" />
                          Vital Signs
                        </h3>
                      </div>
                      <div className="p-6 grid grid-cols-2 gap-4">
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Systolic BP
                          </label>
                          <input
                            type="text"
                            value={selectedReport.vitalSigns?.systolicBp || ""}
                            onChange={(e) => updateVitalSigns("systolicBp", e.target.value)}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter systolic BP"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Diastolic BP
                          </label>
                          <input
                            type="text"
                            value={selectedReport.vitalSigns?.diastolicBp || ""}
                            onChange={(e) => updateVitalSigns("diastolicBp", e.target.value)}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter diastolic BP"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Pulse
                          </label>
                          <input
                            type="text"
                            value={selectedReport.vitalSigns?.pulse || ""}
                            onChange={(e) => updateVitalSigns("pulse", e.target.value)}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter pulse"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Respiratory Rate
                          </label>
                          <input
                            type="text"
                            value={selectedReport.vitalSigns?.respiratoryRate || ""}
                            onChange={(e) => updateVitalSigns("respiratoryRate", e.target.value)}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter respiratory rate"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Temperature
                          </label>
                          <input
                            type="text"
                            value={selectedReport.vitalSigns?.temperature || ""}
                            onChange={(e) => updateVitalSigns("temperature", e.target.value)}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter temperature"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            O2 Saturation
                          </label>
                          <input
                            type="text"
                            value={selectedReport.vitalSigns?.o2Saturation || ""}
                            onChange={(e) => updateVitalSigns("o2Saturation", e.target.value)}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter O2 saturation"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Height
                          </label>
                          <input
                            type="text"
                            value={selectedReport.vitalSigns?.height || ""}
                            onChange={(e) => updateVitalSigns("height", e.target.value)}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter height"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Weight
                          </label>
                          <input
                            type="text"
                            value={selectedReport.vitalSigns?.weight || ""}
                            onChange={(e) => updateVitalSigns("weight", e.target.value)}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter weight"
                          />
                        </div>
                      </div>
                    </div>

                    {/* Clinical Notes */}
                    <div className="bg-white shadow rounded-lg">
                      <div className="px-6 py-4 border-b border-gray-200">
                        <h3 className="text-lg font-medium text-gray-900 flex items-center">
                          <ClipboardList className="h-5 w-5 mr-2 text-purple-600" />
                          Clinical Notes
                        </h3>
                      </div>
                      <div className="p-6 space-y-4">
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Chief Complaint
                          </label>
                          <textarea
                            value={selectedReport.clinicalRecords?.chiefComplaint || ""}
                            onChange={(e) => updateClinicalRecords("chiefComplaint", e.target.value)}
                            rows={3}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter chief complaint"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Current Medications
                          </label>
                          <textarea
                            value={selectedReport.clinicalRecords?.currentMedications || ""}
                            onChange={(e) => updateClinicalRecords("currentMedications", e.target.value)}
                            rows={3}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter current medications"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Evaluation Summary
                          </label>
                          <textarea
                            value={selectedReport.clinicalRecords?.evaluationSummary || ""}
                            onChange={(e) => updateClinicalRecords("evaluationSummary", e.target.value)}
                            rows={3}
                            className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            placeholder="Enter evaluation summary"
                          />
                        </div>
                      </div>
                    </div>

                    {/* Raw OCR Text - Admin Only (Consultation) */}
                    {user?.role === 'admin' && (
                    <div className="bg-white shadow rounded-lg">
                      <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
                        <div>
                          <h3 className="text-lg font-medium text-gray-900 flex items-center">
                            <FileText className="h-5 w-5 mr-2 text-purple-600" />
                            Extracted Raw Text
                          </h3>
                          <p className="text-sm text-gray-500 mt-1">
                            Stored OCR text for audit, review, and re-parsing.
                          </p>
                        </div>
                        <button
                          type="button"
                          onClick={copyRawText}
                          disabled={!selectedReport.rawOcrText}
                          className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          Copy Text
                        </button>
                      </div>
                      <div className="p-6">
                        {selectedReport.rawOcrText ? (
                          <pre className="max-h-96 overflow-auto whitespace-pre-wrap wrap-break-word text-xs leading-5 text-gray-800 bg-gray-50 border border-gray-200 rounded-md p-4 font-mono">
                            {selectedReport.rawOcrText}
                          </pre>
                        ) : (
                          <div className="rounded-md border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500">
                            Raw OCR text is not available for this report.
                          </div>
                        )}
                      </div>
                    </div>
                    )}

                    {/* Treatment Plan */}
                    <div className="space-y-4">
                      <div className="flex items-center justify-between">
                        <h3 className="text-xl font-bold text-gray-900">
                          Treatment Plan
                        </h3>
                        <button
                          onClick={addTreatmentPlanItem}
                          className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                          <Plus className="h-4 w-4 mr-2" />
                          Add Item
                        </button>
                      </div>

                      <div className="bg-white shadow rounded-lg">
                        <div className="overflow-x-auto">
                          <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                              <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                  Type
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                  Code
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                  Action
                                </th>
                              </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                              {(selectedReport.treatmentPlan || []).map((item, index) => (
                                <tr key={index} className="hover:bg-gray-50">
                                  <td className="px-4 py-3">
                                    <input
                                      type="text"
                                      value={item.type}
                                      onChange={(e) =>
                                        updateTreatmentPlanItem(index, "type", e.target.value)
                                      }
                                      className="block w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                      placeholder="Type"
                                    />
                                  </td>
                                  <td className="px-4 py-3">
                                    <input
                                      type="text"
                                      value={item.code}
                                      onChange={(e) =>
                                        updateTreatmentPlanItem(index, "code", e.target.value)
                                      }
                                      className="block w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                      placeholder="Code"
                                    />
                                  </td>
                                  <td className="px-4 py-3">
                                    <button
                                      onClick={() => removeTreatmentPlanItem(index)}
                                      className="text-red-600 hover:text-red-800"
                                      title="Remove item"
                                    >
                                      <Trash2 className="h-4 w-4" />
                                    </button>
                                  </td>
                                </tr>
                              ))}
                              {(!selectedReport.treatmentPlan || selectedReport.treatmentPlan.length === 0) && (
                                <tr>
                                  <td colSpan={3} className="px-4 py-8 text-center text-gray-500">
                                    No treatment plan items. Click &quot;Add Item&quot; to add one.
                                  </td>
                                </tr>
                              )}
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </>
                ) : (
                  <>
                    {/* Lab Information */}
                    <div className="bg-white shadow rounded-lg">
                      <div className="px-6 py-4 border-b border-gray-200">
                        <h3 className="text-lg font-medium text-gray-900 flex items-center">
                          <FileText className="h-5 w-5 mr-2 text-green-600" />
                          Laboratory Information
                        </h3>
                      </div>
                      <div className="p-6 grid grid-cols-2 gap-4">
                        {Object.entries(selectedReport.labInfo).map(
                          ([key, value]) => (
                            <div key={key}>
                              <label className="block text-sm font-medium text-gray-700 mb-1 capitalize">
                                {key.replace(/([A-Z])/g, " $1").trim()}
                              </label>
                              <input
                                type="text"
                                value={value}
                                onChange={(e) =>
                                  updateLabInfo(
                                    key as keyof LabInfo,
                                    e.target.value,
                                  )
                                }
                                className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                placeholder={`Enter ${key
                                  .replace(/([A-Z])/g, " $1")
                                  .trim()
                                  .toLowerCase()}`}
                              />
                            </div>
                          ),
                        )}
                      </div>
                    </div>

                    {/* Raw OCR Text - Admin Only */}
                    {user?.role === 'admin' && (
                    <div className="bg-white shadow rounded-lg">
                      <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
                        <div>
                          <h3 className="text-lg font-medium text-gray-900 flex items-center">
                            <FileText className="h-5 w-5 mr-2 text-purple-600" />
                            Extracted Raw Text
                          </h3>
                          <p className="text-sm text-gray-500 mt-1">
                            Stored OCR text for audit, review, and re-parsing.
                          </p>
                        </div>
                        <button
                          type="button"
                          onClick={copyRawText}
                          disabled={!selectedReport.rawOcrText}
                          className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                          Copy Text
                        </button>
                      </div>
                      <div className="p-6">
                        {selectedReport.rawOcrText ? (
                          <pre className="max-h-96 overflow-auto whitespace-pre-wrap wrap-break-word text-xs leading-5 text-gray-800 bg-gray-50 border border-gray-200 rounded-md p-4 font-mono">
                            {selectedReport.rawOcrText}
                          </pre>
                        ) : (
                          <div className="rounded-md border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500">
                            Raw OCR text is not available for this report.
                          </div>
                        )}
                      </div>
                    </div>
                    )}

                    {/* Test Results by Category */}
                    <div className="space-y-4">
                      <div className="flex items-center justify-between">
                        <h3 className="text-xl font-bold text-gray-900">
                          Test Results
                        </h3>
                        <button
                          onClick={addNewCategory}
                          className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                          <Plus className="h-4 w-4 mr-2" />
                          Add Category
                        </button>
                      </div>

                      {Object.entries(groupedTestResults).map(
                        ([category, tests]) => (
                          <div
                            key={category}
                            className="bg-white shadow rounded-lg"
                          >
                            <div className="px-6 py-4 border-b border-gray-200">
                              <div className="flex items-center justify-between">
                                <h4 className="text-lg font-medium text-gray-900 uppercase tracking-wide">
                                  {category}
                                </h4>
                                <button
                                  onClick={() => addTestResult(category)}
                                  className="inline-flex items-center px-2 py-1 border border-gray-300 rounded text-xs font-medium text-gray-700 bg-white hover:bg-gray-50"
                                >
                                  <Plus className="h-3 w-3 mr-1" />
                                  Add Test
                                </button>
                              </div>
                            </div>
                            <div className="overflow-x-auto">
                              <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                  <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                      Test Name
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                      Result
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                      Unit
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                      Reference Range
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                      Flag
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                      Action
                                    </th>
                                  </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                  {tests.map((test) => (
                                    <tr key={test.id} className="hover:bg-gray-50">
                                      <td className="px-4 py-3">
                                        <input
                                          type="text"
                                          value={test.testName}
                                          onChange={(e) =>
                                            updateTestResult(
                                              test.id,
                                              "testName",
                                              e.target.value,
                                            )
                                          }
                                          className="block w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                          placeholder="Test name"
                                        />
                                      </td>
                                      <td className="px-4 py-3">
                                        <input
                                          type="text"
                                          value={test.result}
                                          onChange={(e) =>
                                            updateTestResult(
                                              test.id,
                                              "result",
                                              e.target.value,
                                            )
                                          }
                                          className="block w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                          placeholder="Result"
                                        />
                                      </td>
                                      <td className="px-4 py-3">
                                        <input
                                          type="text"
                                          value={test.unit}
                                          onChange={(e) =>
                                            updateTestResult(
                                              test.id,
                                              "unit",
                                              e.target.value,
                                            )
                                          }
                                          className="block w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                          placeholder="Unit"
                                        />
                                      </td>
                                      <td className="px-4 py-3">
                                        <input
                                          type="text"
                                          value={test.referenceRange}
                                          onChange={(e) =>
                                            updateTestResult(
                                              test.id,
                                              "referenceRange",
                                              e.target.value,
                                            )
                                          }
                                          className="block w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                                          placeholder="Reference range"
                                        />
                                      </td>
                                      <td className="px-4 py-3">
                                        {renderFlagDropdown(test)}
                                      </td>
                                      <td className="px-4 py-3">
                                        <button
                                          onClick={() => removeTestResult(test.id)}
                                          className="text-red-600 hover:text-red-800"
                                          title="Remove test"
                                        >
                                          <Trash2 className="h-4 w-4" />
                                        </button>
                                      </td>
                                    </tr>
                                  ))}
                                </tbody>
                              </table>
                            </div>
                          </div>
                        ),
                      )}

                      {Object.keys(groupedTestResults).length === 0 && (
                        <div className="bg-white shadow rounded-lg p-8 text-center">
                          <FileText className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                          <p className="text-gray-500">No test results found</p>
                          <button
                            onClick={addNewCategory}
                            className="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                          >
                            <Plus className="h-4 w-4 mr-2" />
                            Add First Category
                          </button>
                        </div>
                      )}
                    </div>
                  </>
                )}
              </div>
            ) : (
              <div className="bg-white shadow rounded-lg p-12 text-center">
                <FileText className="h-16 w-16 text-gray-400 mx-auto mb-4" />
                <h3 className="text-lg font-medium text-gray-900 mb-2">
                  Select a Report
                </h3>
                <p className="text-gray-500">
                  Choose a completed report from the list to start verification
                </p>
              </div>
            )}
          </div>

          {/* Reports List - Right Side */}
          <div className="xl:col-span-2">
            <div className="bg-white shadow rounded-lg">
              <div className="px-6 py-4 border-b border-gray-200">
                <h3 className="text-lg font-medium text-gray-900">
                  Reports ({reports.length})
                </h3>
              </div>
              <div className="p-4 space-y-3 max-h-175 overflow-y-auto">
                {reports.map((report) => (
                  <div
                    key={report.id}
                    onClick={() => {
                      if (report.status === "completed") {
                        setSelectedReport(report);
                        fetchPdfData(report.id);
                      }
                    }}
                    className={`p-3 rounded-lg border cursor-pointer transition-colors ${
                      selectedReport?.id === report.id
                        ? "border-blue-300 bg-blue-50"
                        : report.status === "completed"
                          ? "border-gray-200 hover:border-gray-300 hover:bg-gray-50"
                          : "border-gray-200 bg-gray-50 cursor-not-allowed"
                    }`}
                  >
                    <div className="flex items-center space-x-2">
                      <FileText className="h-4 w-4 text-gray-400 shrink-0" />
                      <span className="text-sm font-medium text-gray-900 truncate">
                        {report.fileName}
                      </span>
                    </div>
                    <div className="mt-2 flex items-center justify-between">
                      <span
                        className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                          report.status === "completed"
                            ? "bg-green-100 text-green-800"
                            : report.status === "processing"
                              ? "bg-yellow-100 text-yellow-800"
                              : "bg-gray-100 text-gray-800"
                        }`}
                      >
                        {report.status}
                      </span>
                      {report.status === "processing" && (
                        <span className="text-xs text-gray-500">
                          {report.processingProgress}%
                        </span>
                      )}
                    </div>
                    {report.status === "processing" && (
                      <div className="mt-2 w-full bg-gray-200 rounded-full h-1">
                        <div
                          className="bg-yellow-600 h-1 rounded-full transition-all duration-300"
                          style={{ width: `${report.processingProgress}%` }}
                        ></div>
                      </div>
                    )}
                    {report.uploader?.name && (
                      <div className="mt-2 text-xs text-gray-500">
                        Uploaded by: {report.uploader?.name}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Add Category Modal */}
        {showCategoryModal && (
          <div
            className="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
          >
            <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
              <div
                className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                aria-hidden="true"
                onClick={() => setShowCategoryModal(false)}
              ></div>
              <span
                className="hidden sm:inline-block sm:align-middle sm:h-screen"
                aria-hidden="true"
              ></span>
              <div className="relative inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div className="sm:flex sm:items-start">
                  <div className="mx-auto shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                    <Plus
                      className="h-6 w-6 text-blue-600"
                      aria-hidden="true"
                    />
                  </div>
                  <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <h3
                      className="text-lg leading-6 font-medium text-gray-900"
                      id="modal-title"
                    >
                      Add New Category
                    </h3>
                    <div className="mt-2">
                      <p className="text-sm text-gray-500">
                        Enter a name for the new test results category.
                      </p>
                    </div>
                    <div className="mt-4">
                      <input
                        type="text"
                        value={newCategoryName}
                        onChange={(e) => setNewCategoryName(e.target.value)}
                        placeholder="Category name (e.g., BIOCHEMISTRY)"
                        className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        onKeyPress={(e) => {
                          if (e.key === "Enter") {
                            handleCategorySubmit();
                          }
                        }}
                      />
                    </div>
                  </div>
                </div>
                <div className="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                  <button
                    type="button"
                    onClick={handleCategorySubmit}
                    disabled={!newCategoryName.trim()}
                    className="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Add Category
                  </button>
                  <button
                    type="button"
                    onClick={() => setShowCategoryModal(false)}
                    className="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:w-auto sm:text-sm"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}
