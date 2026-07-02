<?php
/**
 * State / city / pincode helpers using state-city-api-data-2026-05-12.json
 */

if (!function_exists('getStateCityDataFilePath')) {
    function getStateCityDataFilePath(): string
    {
        return dirname(__DIR__) . '/state-city-api-data-2026-05-12.json';
    }
}

if (!function_exists('loadStateCityApiData')) {
    function loadStateCityApiData(): ?array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $path = getStateCityDataFilePath();
        if (!is_file($path)) {
            $cache = null;
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        $cache = is_array($decoded) ? $decoded : null;
        return $cache;
    }
}

if (!function_exists('normalizeStateName')) {
    function normalizeStateName(string $state): string
    {
        $state = trim($state);
        if ($state === '') {
            return '';
        }
        $data = loadStateCityApiData();
        if ($data && !empty($data['states']) && strlen($state) === 2) {
            foreach ($data['states'] as $row) {
                if (strcasecmp((string) ($row['iso2'] ?? ''), $state) === 0) {
                    return (string) $row['name'];
                }
            }
        }
        return $state;
    }
}

if (!function_exists('resolveStateIso2')) {
    function resolveStateIso2(string $stateValue): string
    {
        $stateValue = trim($stateValue);
        if ($stateValue === '') {
            return '';
        }
        if (strlen($stateValue) === 2) {
            return strtoupper($stateValue);
        }
        $data = loadStateCityApiData();
        if ($data && !empty($data['states'])) {
            foreach ($data['states'] as $row) {
                $name = (string) ($row['name'] ?? '');
                $iso2 = (string) ($row['iso2'] ?? '');
                if ($iso2 !== '' && strcasecmp($name, $stateValue) === 0) {
                    return strtoupper($iso2);
                }
            }
        }
        $legacy = [
            'ODISHA' => 'OR',
            'ORISSA' => 'OR',
            'UTTARAKHAND' => 'UK',
            'UTTARANCHAL' => 'UK',
        ];
        $upper = strtoupper($stateValue);
        return $legacy[$upper] ?? strtoupper($stateValue);
    }
}

if (!function_exists('renderStateCityPincodeFields')) {
    /**
     * @param array<string, mixed> $formData
     */
    function renderStateCityPincodeFields(array $formData = [], array $options = []): void
    {
        $pincode = htmlspecialchars((string) ($formData['pincode'] ?? ''), ENT_QUOTES, 'UTF-8');
        $colClass = $options['col_class'] ?? 'col-md-4';
        ?>
        <div class="<?php echo htmlspecialchars($colClass); ?>">
                    <label class="form-label">State <span class="required-mark">*</span></label>
            <select class="form-select" name="state" id="state" required>
                <option value="">Loading states...</option>
            </select>
            <small class="text-muted" id="stateStatus">
                <i class="fas fa-spinner fa-spin"></i> Loading Indian states...
            </small>
        </div>
        <div class="<?php echo htmlspecialchars($colClass); ?>">
                    <label class="form-label">City / District <span class="required-mark">*</span></label>
            <select class="form-select" name="city" id="city" required disabled>
                <option value="">Select state first</option>
            </select>
            <small class="text-muted" id="cityStatus">
                <i class="fas fa-info-circle"></i> Please select a state first
            </small>
        </div>
        <div class="<?php echo htmlspecialchars($colClass); ?>">
                    <label class="form-label">Pincode <span class="required-mark">*</span></label>
            <input type="text" class="form-control" name="pincode" id="pincode"
                   pattern="[0-9]{6}" maxlength="6" placeholder="6-digit pincode" required
                   value="<?php echo $pincode; ?>">
        </div>
        <?php
    }
}

if (!function_exists('renderStateCityPincodeScript')) {
    /**
     * @param array<string, mixed> $formData
     */
    function renderStateCityPincodeScript(array $formData = []): void
    {
        $data = loadStateCityApiData();
        $boot = [
            'states' => $data['states'] ?? [],
            'cities' => $data['cities'] ?? [],
            'savedState' => resolveStateIso2((string) ($formData['state'] ?? '')),
            'savedCity' => (string) ($formData['city'] ?? ''),
            'savedPincode' => (string) ($formData['pincode'] ?? ''),
        ];
        ?>
        <script>
        window.__STATE_CITY_BOOT__ = <?php echo json_encode($boot, JSON_UNESCAPED_UNICODE); ?>;
        </script>
        <script src="<?php echo APP_URL; ?>/assets/js/state-city-select.js"></script>
        <?php
    }
}
