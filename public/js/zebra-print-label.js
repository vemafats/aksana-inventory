/**
 * Zebra ZD230 label printing via Browser Print (localhost:9100).
 * Label sizes: 40×20 mm (320×160 dots) and 50×25 mm (400×200 dots) at 203 dpi.
 */
(function (global) {
    const BROWSER_PRINT_BASE = 'http://127.0.0.1:9100';

    function escapeZplField(value) {
        return String(value ?? '')
            .replace(/\\/g, '\\\\')
            .replace(/\^/g, '\\^')
            .replace(/~/g, '\\~');
    }

    /**
     * @param {string} itemCode Display text + QR data (barcode/SKU)
     * @param {'40x20'|'50x25'} labelSize
     * @param {number} qty
     */
    function generateZplLabel(itemCode, labelSize, qty) {
        const code = escapeZplField(itemCode);
        const copies = Math.max(1, Math.min(100, parseInt(String(qty), 10) || 1));

        if (labelSize === '40x20') {
            return `^XA^PW320^LL160^FO20,15^A0N,20,20^FD${code}^FS^FO20,45^BQN,2,3^FDMA,${code}^FS^PQ${copies}^XZ`;
        }

        return `^XA^PW400^LL200^FO20,15^A0N,24,24^FD${code}^FS^FO20,50^BQN,2,4^FDMA,${code}^FS^PQ${copies}^XZ`;
    }

    function normalizePrinterDevice(printer) {
        if (printer && typeof printer === 'object') {
            return printer;
        }

        return { name: String(printer ?? 'Zebra') };
    }

    async function fetchAvailablePrinters() {
        const response = await fetch(`${BROWSER_PRINT_BASE}/available`, {
            method: 'GET',
            mode: 'cors',
        });

        if (!response.ok) {
            throw new Error('Zebra Browser Print tidak merespons. Pastikan aplikasi sudah berjalan.');
        }

        const printers = await response.json();

        if (!Array.isArray(printers) || printers.length === 0) {
            throw new Error('Tidak ada printer Zebra yang tersedia.');
        }

        return printers.map(normalizePrinterDevice);
    }

    async function sendZplToPrinter(device, zpl) {
        const response = await fetch(`${BROWSER_PRINT_BASE}/write`, {
            method: 'POST',
            mode: 'cors',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                device,
                data: zpl,
            }),
        });

        if (!response.ok) {
            throw new Error('Gagal mengirim data ke printer.');
        }
    }

    /**
     * @param {string} itemCode
     * @param {'40x20'|'50x25'} labelSize
     * @param {number} qty
     */
    async function printZplLabel(itemCode, labelSize, qty) {
        const printers = await fetchAvailablePrinters();
        const zpl = generateZplLabel(itemCode, labelSize, qty);
        await sendZplToPrinter(printers[0], zpl);

        return { printer: printers[0], zpl };
    }

    function printLabelModal() {
        return {
            selectedBarcode: '',
            labelSize: '50x25',
            qty: 1,
            printing: false,
            status: '',
            statusClass: '',

            get selectedItemLabel() {
                const select = this.$refs.itemSelect;
                if (!select || !this.selectedBarcode) {
                    return this.selectedBarcode;
                }

                const option = select.querySelector(`option[value="${CSS.escape(this.selectedBarcode)}"]`);
                return option ? option.textContent.trim() : this.selectedBarcode;
            },

            async printLabel() {
                if (!this.selectedBarcode || this.printing) {
                    return;
                }

                this.printing = true;
                this.status = 'Menghubungkan ke printer...';
                this.statusClass = 'text-[#49586B]';

                try {
                    const result = await printZplLabel(this.selectedBarcode, this.labelSize, this.qty);
                    const printerName = result.printer?.name ?? 'printer';
                    this.status = `Berhasil! ${this.qty} label dicetak ke ${printerName}.`;
                    this.statusClass = 'text-green-600 font-semibold';
                } catch (error) {
                    const message = error instanceof Error ? error.message : 'Terjadi kesalahan saat mencetak.';
                    this.status = `Gagal: ${message}`;
                    this.statusClass = 'text-red-600 font-semibold';
                    console.error('Print error:', error);
                } finally {
                    this.printing = false;
                }
            },
        };
    }

    global.AksanaZebraPrint = {
        escapeZplField,
        generateZplLabel,
        printZplLabel,
        fetchAvailablePrinters,
    };

    global.printLabelModal = printLabelModal;
})(window);
