<?php
class Analytics {
    private $storageDir;
    private $analyticsEnabled;
    
    public function __construct() {
        $this->analyticsEnabled = defined('ENABLE_ANALYTICS') ? ENABLE_ANALYTICS : false;
        $this->storageDir = STORAGE_DIR . '/analytics';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }
    
    // Track page views
    public function trackPageView($page) {
        if (!$this->analyticsEnabled || !(defined('TRACK_VISITORS') && TRACK_VISITORS)) {
            return false;
        }

        $visitorData = $this->loadData('visitors.json');
        $date = date('Y-m-d');
        $hour = date('H');
        
        if (!isset($visitorData[$date])) $visitorData[$date] = [];
        if (!isset($visitorData[$date][$hour])) {
            $visitorData[$date][$hour] = ['total' => 0, 'pages' => []];
        }
        
        $visitorData[$date][$hour]['total']++;
        if (!isset($visitorData[$date][$hour]['pages'][$page])) {
            $visitorData[$date][$hour]['pages'][$page] = 0;
        }
        $visitorData[$date][$hour]['pages'][$page]++;
        
        $this->saveData('visitors.json', $visitorData);

        return true;
    }
    
    // Track component usage
    public function trackComponentUsage($componentType) {
        if (!$this->analyticsEnabled || !(defined('TRACK_COMPONENTS') && TRACK_COMPONENTS)) {
            return false;
        }

        $componentData = $this->loadData('components.json');
        if (!isset($componentData[$componentType])) $componentData[$componentType] = 0;
        $componentData[$componentType]++;
        $this->saveData('components.json', $componentData);

        return true;
    }
    
    // Track theme usage
    public function trackThemeUsage($theme) {
        if (!$this->analyticsEnabled || !(defined('TRACK_THEMES') && TRACK_THEMES)) {
            return false;
        }

        $themeData = $this->loadData('themes.json');
        if (!isset($themeData[$theme])) $themeData[$theme] = 0;
        $themeData[$theme]++;
        $this->saveData('themes.json', $themeData);

        return true;
    }
    
    // Track form usage
    public function trackFormUsage($formId, $isSubmission = false) {
        if (!$this->analyticsEnabled || !(defined('TRACK_FORM_USAGE') && TRACK_FORM_USAGE)) {
            return false;
        }
        
        $formData = $this->loadData('form_usage.json');
        
        if (!isset($formData[$formId])) {
            $formData[$formId] = [
                'views' => 0,
                'submissions' => 0,
                'last_used' => time()
            ];
        }
        
        if ($isSubmission) {
            $formData[$formId]['submissions']++;
        } else {
            $formData[$formId]['views']++;
        }
        
        $formData[$formId]['last_used'] = time();
        $this->saveData('form_usage.json', $formData);

        return true;
    }
    
    // Helper methods to load and save data
    private function loadData($filename) {
        $filePath = $this->storageDir . '/' . $filename;
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            return json_decode($content, true) ?? [];
        }
        return [];
    }
    
    private function saveData($filename, $data) {
        $filePath = $this->storageDir . '/' . $filename;
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function isEnabled() {
        return $this->analyticsEnabled;
    }
    
    // Analytics retrieval methods
    public function getLiveVisitors() {
        $visitorData = $this->loadData('visitors.json');
        $date = date('Y-m-d');
        $hour = date('H');
        $prevHour = date('H', strtotime('-1 hour'));
        
        $currentHourVisitors = isset($visitorData[$date][$hour]['total']) ? 
            $visitorData[$date][$hour]['total'] : 0;
        $prevHourVisitors = isset($visitorData[$date][$prevHour]['total']) ? 
            $visitorData[$date][$prevHour]['total'] : 0;
        
        return $currentHourVisitors + $prevHourVisitors;
    }
    
    public function getPopularComponents($limit = 10) {
        $componentData = $this->loadData('components.json');
        arsort($componentData);
        return array_slice($componentData, 0, $limit, true);
    }
    
    public function getPopularThemes() {
        $themeData = $this->loadData('themes.json');
        arsort($themeData);
        return $themeData;
    }
    
    public function getPopularForms($limit = 10) {
        $formData = $this->loadData('form_usage.json');
        uasort($formData, function($a, $b) {
            return $b['views'] - $a['views'];
        });
        return array_slice($formData, 0, $limit, true);
    }
}