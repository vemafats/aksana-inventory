/**
 * Zebra GC420 label printing via Browser Print (localhost:9100).
 * Label sizes: 40×20 mm (320×160 dots) and 50×25 mm (400×200 dots) at 203 dpi.
 */
(function (global) {
    function printLabelModal() {
        return {
            open: false,
            selectedBarcode: '',
            labelSize: '40x20',
            qty: 1,
            printing: false,
            status: '',
            statusOk: false,
            selectedDevice: null,

            generateZPL() {
                const code = this.selectedBarcode;
                if (this.labelSize === '40x20') {
                    return '^XA\n^CI28\n^PW320\n^LL160\n^LH0,0\n^FO10,10^A0N,28,28^FD' + code + '^FS\n^FO10,50^BQN,2,3^FDMA,' + code + '^FS\n^PQ' + this.qty + '\n^XZ';
                }

                return '^XA\n^CI28\n^PW400\n^LL200\n^LH0,0\n^FO10,10^A0N,32,32^FD' + code + '^FS\n^FO10,55^BQN,2,4^FDMA,' + code + '^FS\n^PQ' + this.qty + '\n^XZ';
            },

            async findPrinter() {
                return new Promise((resolve, reject) => {
                    try {
                        fetch('http://127.0.0.1:9100/available')
                            .then((r) => {
                                if (!r.ok) {
                                    throw new Error('Browser Print tidak merespon');
                                }

                                return r.text();
                            })
                            .then((text) => {
                                console.log('[ZPL] Available printers response:', text);
                                try {
                                    const data = JSON.parse(text);
                                    let printers = [];
                                    if (Array.isArray(data)) {
                                        printers = data;
                                    } else if (data.printer && Array.isArray(data.printer)) {
                                        printers = data.printer;
                                    } else if (data.deviceList && Array.isArray(data.deviceList)) {
                                        printers = data.deviceList;
                                    } else if (typeof data === 'object') {
                                        printers = [data];
                                    }

                                    if (printers.length === 0) {
                                        reject(new Error('Tidak ada printer terdeteksi'));

                                        return;
                                    }

                                    console.log('[ZPL] Found printers:', JSON.stringify(printers));
                                    resolve(printers[0]);
                                } catch (parseErr) {
                                    console.log('[ZPL] Non-JSON response, treating as device:', text);
                                    resolve({ name: text.trim(), uid: text.trim() });
                                }
                            })
                            .catch((err) => {
                                reject(new Error('Zebra Browser Print tidak berjalan: ' + err.message));
                            });
                    } catch (e) {
                        reject(e);
                    }
                });
            },

            async sendToPrinter(printer, zpl) {
                console.log('[ZPL] Sending to printer:', JSON.stringify(printer));
                console.log('[ZPL] ZPL data:', zpl);

                try {
                    const res = await fetch('http://127.0.0.1:9100/write', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            device: printer,
                            data: zpl,
                        }),
                    });
                    if (res.ok) {
                        console.log('[ZPL] Approach 1 (JSON write) succeeded');

                        return true;
                    }
                    console.log('[ZPL] Approach 1 failed:', res.status);
                } catch (e) {
                    console.log('[ZPL] Approach 1 error:', e.message);
                }

                try {
                    const res = await fetch('http://127.0.0.1:9100/write', {
                        method: 'POST',
                        headers: { 'Content-Type': 'text/plain' },
                        body: zpl,
                    });
                    if (res.ok) {
                        console.log('[ZPL] Approach 2 (plain text write) succeeded');

                        return true;
                    }
                    console.log('[ZPL] Approach 2 failed:', res.status);
                } catch (e) {
                    console.log('[ZPL] Approach 2 error:', e.message);
                }

                try {
                    const deviceName = printer.name || printer.uid || printer;
                    const res = await fetch('http://127.0.0.1:9100/write?device=' + encodeURIComponent(deviceName), {
                        method: 'POST',
                        headers: { 'Content-Type': 'text/plain' },
                        body: zpl,
                    });
                    if (res.ok) {
                        console.log('[ZPL] Approach 3 (query param device) succeeded');

                        return true;
                    }
                    console.log('[ZPL] Approach 3 failed:', res.status);
                } catch (e) {
                    console.log('[ZPL] Approach 3 error:', e.message);
                }

                throw new Error('Semua metode pengiriman gagal. Cek console browser (F12) untuk detail.');
            },

            async printLabel() {
                if (!this.selectedBarcode) {
                    return;
                }

                this.printing = true;
                this.status = 'Mencari printer...';
                this.statusOk = false;

                try {
                    const printer = await this.findPrinter();
                    this.status = 'Mengirim ke printer...';

                    const zpl = this.generateZPL();
                    console.log('[ZPL] Generated ZPL:', zpl);

                    await this.sendToPrinter(printer, zpl);

                    this.status = 'Berhasil! ' + this.qty + ' label dikirim ke printer.';
                    this.statusOk = true;
                } catch (e) {
                    this.status = e.message;
                    this.statusOk = false;
                    console.error('[ZPL] Print error:', e);
                } finally {
                    this.printing = false;
                }
            },
        };
    }

    global.printLabelModal = printLabelModal;
})(window);
