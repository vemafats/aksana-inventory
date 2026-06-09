/**
 * Zebra GC420 label printing via Browser Print (port 9100).
 * Registers window.printLabelModal() for Alpine x-data="printLabelModal()".
 */
(function (global) {
    const AVAILABLE_PATHS = [
        'http://127.0.0.1:9100/available',
        'http://localhost:9100/available',
    ];

    const ZPL_TEMPLATE_40x30 =
        '^XA\n^CI28\n^PW319\n^LL240\n^LH0,0\n^FO36,220^A0N,22,22^FD{CODE}^FS\n^FO72,30^BQN,2,8^FDMA,{CODE}^FS\n^PQ{QTY}\n^XZ';

    const ZPL_TEMPLATE_50x25 =
        '^XA\n^CI28\n^PW400\n^LL200\n^LH0,0\n^FO10,10^A0N,32,32^FD{CODE}^FS\n^FO10,55^BQN,2,4^FDMA,{CODE}^FS\n^PQ{QTY}\n^XZ';

    const ZPL_TEMPLATE_30x20 =
        '^XA\n^CI28\n^PW240\n^LL160\n^LH0,0\n^FO20,140^A0N,18,18^FD{CODE}^FS\n^FO50,10^BQN,2,5^FDMA,{CODE}^FS\n^PQ{QTY}\n^XZ';

    function zplStorageKey(size) {
        return 'aksana_zpl_' + size;
    }

    function parsePrinterFromAvailable(text) {
        console.log('[ZPL] Parsing available response:', text);

        try {
            const data = JSON.parse(text);

            if (Array.isArray(data)) {
                console.log('[ZPL] Parsed as array, count:', data.length);
                return data[0] ?? null;
            }

            if (data && Array.isArray(data.printer)) {
                console.log('[ZPL] Parsed printer array, count:', data.printer.length);
                return data.printer[0] ?? null;
            }

            if (data && Array.isArray(data.deviceList)) {
                console.log('[ZPL] Parsed deviceList, count:', data.deviceList.length);
                return data.deviceList[0] ?? null;
            }

            if (data && typeof data === 'object') {
                console.log('[ZPL] Parsed as single printer object:', JSON.stringify(data));
                return data;
            }
        } catch (parseError) {
            console.log('[ZPL] Response is not JSON, using plain text as device name');
        }

        const trimmed = String(text ?? '').trim();
        if (trimmed === '') {
            return null;
        }

        return { name: trimmed, uid: trimmed };
    }

    async function fetchAvailable() {
        let lastError = null;

        for (const url of AVAILABLE_PATHS) {
            try {
                console.log('[ZPL] Step 1: GET', url);
                const response = await fetch(url, { method: 'GET', mode: 'cors' });
                const body = await response.text();

                console.log('[ZPL] Available status:', response.status);
                console.log('[ZPL] Available body:', body);

                if (!response.ok) {
                    lastError = new Error('HTTP ' + response.status + ' from ' + url);
                    continue;
                }

                const baseUrl = url.replace(/\/available$/, '');
                const printer = parsePrinterFromAvailable(body);

                if (!printer) {
                    lastError = new Error('Tidak ada printer terdeteksi dari ' + url);
                    continue;
                }

                console.log('[ZPL] Selected printer:', JSON.stringify(printer));
                return { baseUrl, printer, raw: body };
            } catch (error) {
                console.log('[ZPL] Available fetch error for', url, ':', error.message);
                lastError = error;
            }
        }

        throw new Error('Browser Print tidak merespon. ' + (lastError?.message ?? 'Cek aplikasi Zebra Browser Print.'));
    }

    async function tryWrite(baseUrl, zpl, printer) {
        const writeUrl = baseUrl + '/write';
        const attempts = [
            {
                label: 'plain text body',
                init: { method: 'POST', headers: { 'Content-Type': 'text/plain' }, body: zpl },
            },
            {
                label: 'JSON device+data',
                init: {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ device: printer, data: zpl }),
                },
            },
            {
                label: 'query param device',
                init: {
                    method: 'POST',
                    headers: { 'Content-Type': 'text/plain' },
                    body: zpl,
                    url: writeUrl + '?device=' + encodeURIComponent(printer?.name || printer?.uid || String(printer)),
                },
            },
        ];

        for (const attempt of attempts) {
            const url = attempt.url ?? writeUrl;
            try {
                console.log('[ZPL] Step 3: POST', url, '(' + attempt.label + ')');
                const response = await fetch(url, attempt.init);
                const responseText = await response.text().catch(() => '');

                console.log('[ZPL] Write status:', response.status, 'response:', responseText);

                if (response.ok) {
                    console.log('[ZPL] Write succeeded via', attempt.label);
                    return true;
                }
            } catch (error) {
                console.log('[ZPL] Write error (' + attempt.label + '):', error.message);
            }
        }

        return false;
    }

    function printLabelModal() {
        return {
            open: false,
            selectedBarcode: '',
            labelSize: '30x20',
            qty: 1,
            printing: false,
            statusMsg: '',
            statusSuccess: false,
            showZplEditor: false,
            customZpl: '',
            savedTemplates: {},
            saveConfirm: false,

            getDefaultZpl(size) {
                if (size === '40x30') {
                    return ZPL_TEMPLATE_40x30;
                }

                if (size === '30x20') {
                    return ZPL_TEMPLATE_30x20;
                }

                return ZPL_TEMPLATE_50x25;
            },

            loadSavedZpl(size) {
                const key = zplStorageKey(size);
                const saved = localStorage.getItem(key);

                if (saved) {
                    this.savedTemplates[size] = saved;
                    console.log('[ZPL] Loaded saved template for', size);
                    return saved;
                }

                return this.getDefaultZpl(size);
            },

            saveZpl() {
                const key = zplStorageKey(this.labelSize);
                localStorage.setItem(key, this.customZpl);
                this.savedTemplates[this.labelSize] = this.customZpl;
                this.saveConfirm = true;
                console.log('[ZPL] Saved template for', this.labelSize);
                setTimeout(() => {
                    this.saveConfirm = false;
                }, 2000);
            },

            resetZpl() {
                const key = zplStorageKey(this.labelSize);
                localStorage.removeItem(key);
                delete this.savedTemplates[this.labelSize];
                this.customZpl = this.getDefaultZpl(this.labelSize);
                console.log('[ZPL] Reset template to default for', this.labelSize);
            },

            init() {
                this.open = true;
                this.customZpl = this.loadSavedZpl(this.labelSize);

                this.$watch('labelSize', (val) => {
                    this.customZpl = this.loadSavedZpl(val);
                });

                this.$watch('open', (val) => {
                    if (!val) {
                        this.$wire.closePrintModal();
                        return;
                    }

                    this.customZpl = this.loadSavedZpl(this.labelSize);
                });
            },

            generateZPL() {
                const code = this.selectedBarcode;
                const saved = localStorage.getItem(zplStorageKey(this.labelSize));
                let template = saved || this.getDefaultZpl(this.labelSize);

                if (this.customZpl.trim() !== '') {
                    template = this.customZpl;
                }

                const zpl = template
                    .replace(/\{CODE\}/g, code)
                    .replace(/\{QTY\}/g, this.qty.toString());

                console.log('[ZPL] generateZPL labelSize:', this.labelSize, 'code:', code, 'qty:', this.qty, 'fromSaved:', !!saved);

                return zpl;
            },

            async printLabel() {
                if (!this.selectedBarcode) {
                    console.log('[ZPL] printLabel aborted: no barcode selected');
                    return;
                }

                this.printing = true;
                this.statusMsg = 'Mencari printer...';
                this.statusSuccess = false;

                try {
                    console.log('[ZPL] printLabel started');

                    const { baseUrl, printer } = await fetchAvailable();

                    const zpl = this.generateZPL();
                    console.log('[ZPL] Generated ZPL:\n', zpl);

                    this.statusMsg = 'Mengirim ke printer...';

                    const sent = await tryWrite(baseUrl, zpl, printer);

                    if (!sent) {
                        throw new Error('Gagal mengirim ke printer. Cek console F12 untuk detail.');
                    }

                    this.statusMsg = 'Berhasil! ' + this.qty + ' label dikirim ke printer.';
                    this.statusSuccess = true;
                    console.log('[ZPL] printLabel completed successfully');
                } catch (error) {
                    this.statusMsg = error?.message ?? 'Terjadi kesalahan saat mencetak.';
                    this.statusSuccess = false;
                    console.error('[ZPL] printLabel error:', error);
                } finally {
                    this.printing = false;
                }
            },
        };
    }

    global.printLabelModal = printLabelModal;

    console.log('[ZPL] zebra-print-label.js loaded, printLabelModal ready');
})(window);
