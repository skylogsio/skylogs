package util_models

type Pagination struct {
	Page     int64
	PageSize int64
	SortBy   string
	SortType SortType
}

type SortType string

const (
	SortAscending  SortType = "asc"
	SortDescending SortType = "des"
)

type ResultIndex struct {
	CurrentPage int64
	TotalPage   int64
	TotalData   int64
	Data        any
}
