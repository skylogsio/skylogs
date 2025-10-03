import { Card, CardContent, Box, Skeleton } from "@mui/material";
import { grey } from "@mui/material/colors";

const StatusCardSkeleton = () => {
  return (
    <Card
      sx={{
        width: 340,
        borderRadius: 4,
        position: "relative",
        overflow: "hidden",
        boxShadow: "none",
        border: 2,
        borderColor: grey[300]
      }}
    >
      <CardContent sx={{ padding: "16px !important", height: "100%" }}>
        <Box
          sx={{
            display: "flex",
            justifyContent: "space-between",
            alignItems: "flex-start",
            mb: 1
          }}
        >
          <Skeleton
            animation="wave"
            variant="text"
            width={160}
            height={28}
            sx={{ fontSize: "1.1rem" }}
          />
          <Skeleton animation="wave" variant="circular" width={40} height={40} />
        </Box>
        <Box sx={{ display: "flex", gap: 1, mb: 2, flexWrap: "wrap" }}>
          <Skeleton
            animation="wave"
            variant="rounded"
            width={100}
            height={24}
            sx={{ borderRadius: "12px" }}
          />
          <Skeleton
            animation="wave"
            variant="rounded"
            width={80}
            height={24}
            sx={{ borderRadius: "12px" }}
          />
        </Box>
        <Box sx={{ mb: 1.5 }}>
          <Skeleton animation="wave" variant="text" width={80} height={16} sx={{ mb: 0.5 }} />
          <Skeleton
            animation="wave"
            variant="rounded"
            width="100%"
            height={6}
            sx={{ borderRadius: 1.5 }}
          />
        </Box>
        <Box sx={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <Skeleton animation="wave" variant="text" width={120} height={16} />
          <Skeleton animation="wave" variant="circular" width={8} height={8} />
        </Box>
      </CardContent>
    </Card>
  );
};

export default StatusCardSkeleton;
