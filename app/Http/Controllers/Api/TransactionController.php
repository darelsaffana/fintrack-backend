<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->transactions()->with('category')->orderByDesc('date');

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }
        if ($request->has('from')) {
            $query->whereDate('date', '>=', $request->query('from'));
        }
        if ($request->has('to')) {
            $query->whereDate('date', '<=', $request->query('to'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        // 1. Simpan data transaksi terikat dengan user yang login
        $transaction = $request->user()->transactions()->create($validator->validated());

        // Load data category agar nama kategori terbaca
        $transaction->load('category');

        // 2. Tentukan emoji dan teks berdasarkan tipe transaksi
        $isIncome = $transaction->type == 'income';
        $tipeText = $isIncome ? '💰 PEMASUKAN' : '💸 PENGELUARAN';
        $iconTipe = $isIncome ? '🟢' : '🔴';

        // Format tanggal transaksi menjadi format Indonesia (DD-MM-YYYY)
        $tanggalFormatted = date('d-m-Y', strtotime($transaction->date));

        // Mengubah jam input database (UTC) langsung menjadi zona waktu Indonesia (WIB) secara akurat
        $jamFormatted = $transaction->created_at->timezone('Asia/Jakarta')->format('H:i');

        // Format angka nominal menjadi Rupiah
        $nominal = number_format($transaction->amount, 0, ',', '.');
        $namaKategori = $transaction->category ? $transaction->category->name : 'Umum/Tanpa Kategori';

        // 3. Susun format teks notifikasi WhatsApp yang rapi dan pas jamnya
        $pesanWA = "📌 *NOTIFIKASI TRANSAKSI FINTRACK*\n"
                 . "---------------------------------------------\n\n"
                 . "Halo! Berikut adalah rincian transaksi terbaru kamu:\n\n"
                 . "{$iconTipe} *Status:* {$tipeText}\n"
                 . "🗂️ *Kategori:* {$namaKategori}\n"
                 . "💵 *Nominal:* Rp {$nominal}\n"
                 . "📅 *Tanggal:* {$tanggalFormatted}\n"
                 . "⏰ *Waktu:* {$jamFormatted} WIB\n"
                 . "📝 *Keterangan:* " . ($transaction->description ?? '-') . "\n\n"
                 . "---------------------------------------------\n"
                 . "_Pesan ini dikirim otomatis oleh sistem FinTrack App._";

        // 4. Nomor WhatsApp tujuan untuk testing (Nomor kamu yang terhubung di Fonnte)
        $nomorTujuan = '08976323558';

        // Jalankan fungsi kirim WhatsApp
        $this->sendWhatsAppNotification($nomorTujuan, $pesanWA);

        // 5. Kembalikan response json sukses ke Flutter
        return response()->json($transaction, 201);
    }

    public function update(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'sometimes|required|in:income,expense',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'date' => 'sometimes|required|date',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $transaction->update($validator->validated());

        return response()->json($transaction->load('category'));
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $transaction->delete();

        return response()->json(['message' => 'Transaksi dihapus']);
    }

    /**
     * Fungsi Helper untuk mengirim request API Gateway Fonnte
     */
    private function sendWhatsAppNotification($phone, $message)
    {
        $token = env('WHATSAPP_TOKEN');

        $response = Http::withHeaders([
            'Authorization' => $token
        ])->post('https://api.fonnte.com/send', [
            'target'      => $phone,
            'message'     => $message,
            'countryCode' => '62',
        ]);

        return $response->json();
    }
}
