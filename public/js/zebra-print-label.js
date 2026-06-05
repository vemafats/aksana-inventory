/**
 * Zebra GC420 label printing via Browser Print (port 9100).
 * Registers window.printLabelModal() for Alpine x-data="printLabelModal()".
 */
(function (global) {
    const AVAILABLE_PATHS = [
        'http://127.0.0.1:9100/available',
        'http://localhost:9100/available',
    ];

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
            labelSize: '40x30',
            qty: 1,
            printing: false,
            statusMsg: '',
            statusSuccess: false,
            showZplEditor: false,
            customZpl: '',

            getDefaultZpl(size) {
                const code = '{CODE}';
                if (size === '40x30') {
                    return '^XA\n^CI28\n^PW320\n^LL240\n^LH0,0\n^FO10,10^A0N,28,28^FD' + code + '^FS\n^FO10,50^BQN,2,3^FDMA,' + code + '^FS\n^PQ{QTY}\n^XZ';
                }

                return '^XA\n^CI28\n^PW400\n^LL200\n^LH0,0\n^FO10,10^A0N,32,32^FD' + code + '^FS\n^FO10,55^BQN,2,4^FDMA,' + code + '^FS\n^PQ{QTY}\n^XZ';
            },

            resetZpl() {
                this.customZpl = this.getDefaultZpl(this.labelSize);
            },

            init() {
                this.open = true;
                this.customZpl = this.getDefaultZpl(this.labelSize);

                this.$watch('labelSize', (val) => {
                    if (!this.showZplEditor || this.customZpl === '' || this.customZpl === this.getDefaultZpl(val === '40x30' ? '50x25' : '40x30')) {
                        this.customZpl = this.getDefaultZpl(val);
                    }
                });

                this.$watch('open', (val) => {
                    if (!val) {
                        this.$wire.closePrintModal();
                        return;
                    }

                    if (this.customZpl === '') {
                        this.customZpl = this.getDefaultZpl(this.labelSize);
                    }
                });
            },

            generateZPL() {
                const code = this.selectedBarcode;
                let template;

                if (this.showZplEditor && this.customZpl.trim() !== '') {
                    template = this.customZpl;
                } else if (this.labelSize === '40x30') {
                    template = '^XA\n^CI28\n^PW320\n^LL240\n^LH0,0\n^FO10,10^A0N,28,28^FD{CODE}^FS\n^FO10,50^BQN,2,3^FDMA,{CODE}^FS\n^PQ{QTY}\n^XZ';
                } else {
                    template = '^XA\n^CI28\n^PW400\n^LL200\n^LH0,0\n^FO10,10^A0N,32,32^FD{CODE}^FS\n^FO10,55^BQN,2,4^FDMA,{CODE}^FS\n^PQ{QTY}\n^XZ';
                }

                const zpl = template
                    .replace(/\{CODE\}/g, code)
                    .replace(/\{QTY\}/g, this.qty.toString());

                console.log('[ZPL] generateZPL labelSize:', this.labelSize, 'code:', code, 'qty:', this.qty, 'custom:', this.showZplEditor);

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
