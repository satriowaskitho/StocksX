<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use App\Filament\Resources\OrderResource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Forms\Components\Actions\Action;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\RestoreBulkAction;
use App\Filament\Resources\OrderResource\Pages;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use AymanAlhattami\FilamentDateScopesFilter\DateScopeFilter;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = 'Stocks Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Client')
                    ->description('Client details')
                    ->collapsible()
                    ->schema([
                        Select::make('client_name')
                        ->options(User::pluck('name', 'name')) // key = name, value = name
                        // ->relationship(name: 'user', titleAttribute: 'name')
                        ->required()
                        ->native(false)
                        ->searchable()
                        ->preload()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            // $user = User::find($state);
                            $user = User::where('name', $state)->first();
                            if ($user) {
                                $set('client_address', $user->email);
                            }
                        }),
                        Hidden::make('client_phone')                            
                            ->required()
                            ->default('081122334455')
                            ->dehydrated(),
                        TextInput::make('client_address')
                            ->readOnly()
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                    ])->columns(2),

                Section::make('Products')
                    ->description('Ordered products')
                    ->collapsible()
                    ->schema([
                        Repeater::make('orderProducts')
                            ->label('')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->relationship(name: 'product', titleAttribute: 'name')
                                    ->required()
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('price', $product->price);
                                        }
                                    })
                                    // Disable options that are already selected in other rows
                                    ->disableOptionWhen(function ($value, $state, Get $get) {
                                        return collect($get('../*.product_id'))
                                            ->reject(fn($id) => $id == $state)
                                            ->filter()
                                            ->contains($value);
                                    }),
                                TextInput::make('quantity')
                                    ->default(1)
                                    ->integer()
                                    ->required()
                                    ->minValue(1)
                                    ->helperText(function (Get $get) {
                                      $productId = $get('product_id');
                                      if ($productId) {
                                          $product = \App\Models\Product::find($productId);
                                          if ($product) {
                                              return 'Available: ' . $product->quantity;
                                          }
                                      }
                                      return null;
                                    }),
                                TextInput::make('price')
                                    ->numeric()
                                    ->prefix('IDR')
                                    ->readOnly()
                                    ->disabled()
                                    ->dehydrated()
                                    ->required(),
                            ])
                            ->columns(3)
                            // Repeatable field is live so that it will trigger the state update on each change
                            ->live()
                            // After adding a new row, we need to update the totals
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateTotals($get, $set);
                            })
                            // After deleting a row, we need to update the totals
                            ->deleteAction(
                                fn(Action $action) => $action->after(fn(Get $get, Set $set) => self::updateTotals($get, $set)),
                            )
                            // Disable reordering
                            ->reorderable(false),
                    ]),
                Section::make('Total')
                    ->description('Total spending')
                    ->collapsible()
                    ->schema([
                        TextInput::make('total')
                            ->prefix('IDR')
                            ->required()
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->readOnly()
                            // This enables us to display the subtotal on the edit page load
                            ->afterStateHydrated(function (Get $get, Set $set) {
                                self::updateTotals($get, $set);
                            }),
                    ]),
                Section::make('Delivered')
                    ->description('The client had paid and got his products')
                    ->hiddenOn(['create', 'edit'])
                    ->schema([
                        Forms\Components\Toggle::make('delivered')
                    ]),
                Section::make('Canceled')
                    ->description('The client had canceled and return his products')
                    ->hiddenOn(['create', 'edit'])
                    ->schema([
                        Forms\Components\Toggle::make('canceled')
                            ->onColor('danger'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Cashier')
                    ->hidden(!auth()->user()->hasRole('super_admin')),
                TextColumn::make('client_name')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->getStateUsing(function ($record) {
                        return $record->orderProducts->sum('quantity');
                    }),
                TextColumn::make('client_phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('client_address')
                    ->searchable()
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
                // TextColumn::make('total')
                //     // ->money('IDR')
                //     ->sortable()
                //     ->formatStateUsing(function ($state) {
                //       return 'IDR ' . number_format($state, 2, ',', '.');
                //     }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->money('IDR')
                    // ->summarize(Sum::make()),
                    ->summarize(
                      Sum::make()->formatStateUsing(fn($state) => 'IDR ' . number_format($state, 2, ',', '.'))
                    )
                    ->formatStateUsing(function ($state) {
                      return 'IDR ' . number_format($state, 2, ',', '.');
                    }),
                ToggleColumn::make('delivered'),
                ToggleColumn::make('canceled')
                ->afterStateUpdated(function (bool $state, Order $record) {
                    foreach ($record->orderProducts as $orderProduct) {
                        $product = Product::find($orderProduct->product_id);
            
                        if ($product) {
                            if ($state) {
                                // Canceled: Add back quantity
                                $product->increment('quantity', $orderProduct->quantity);
                            } else {
                                // Un-canceled: Decrease quantity again
                                $product->decrement('quantity', $orderProduct->quantity);
                            }
                        }
                    }
                })
                ->onColor('danger'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
                DateScopeFilter::make('created_at'),
            ])
            ->actions([
                ViewAction::make(),
                // EditAction::make(),
                DeleteAction::make()
                    // ->action(function ($record) {
                    //     // First, loop through the related order products
                    //     foreach ($record->orderProducts as $order) {
                    //         // Find the related product
                    //         $product = Product::find($order->product_id);
                
                    //         if ($product) {
                    //             // Increment the quantity of the product
                    //             $product->increment('quantity', $order->quantity);
                    //         }
                    //     }
                
                    //     // Now, delete the order record itself
                    //     $record->delete();
                    // }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            // 'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->client_name; // Show client name as title
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'client_name',
            'client_phone',
            'client_address',
            'total',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Phone' => $record->client_phone,
            'Address' => $record->client_address,
            'Total' => $record->total,
            'Delivered' => $record->delivered ? 'Yes' : 'No',
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return OrderResource::getUrl('view', ['record' => $record]);
    }

    public static function getNavigationBadge(): ?string
    {
        $undeliveredOrdersCount = static::getModel()::where('delivered', false)
            ->withoutTrashed()
            ->count();
        return $undeliveredOrdersCount > 0 ? (string) $undeliveredOrdersCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('delivered', false)->count() > 10 ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'The number of undelivered orders';
    }


    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    // / This function updates totals based on the selected products and quantities
    public static function updateTotals(Get $get, Set $set): void
    {
        // Retrieve all selected products and remove empty rows
        $selectedProducts = collect($get('orderProducts'))->filter(fn($item) => !empty($item['product_id']) && !empty($item['quantity']));

        // Retrieve prices for all selected products
        $prices = Product::find($selectedProducts->pluck('product_id'))->pluck('price', 'id');

        // Calculate subtotal based on the selected products and quantities
        $subtotal = $selectedProducts->reduce(function ($subtotal, $product) use ($prices) {
            return $subtotal + ($prices[$product['product_id']] * $product['quantity']);
        }, 0);

        // Update the state with the new values
        // $set('subtotal', number_format($subtotal, 2, '.', ''));
        $set('total', number_format($subtotal, 2, '.', ''));
        // $set('total', number_format($subtotal + ($subtotal * ($get('taxes') / 100)), 2, '.', ''));
    }    
}
